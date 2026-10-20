#!/usr/bin/env bash
# Companion to update-vps.ps1, for the existing Vmail/Traefik installation.
set -euo pipefail
umask 077

[[ $(id -u) == 0 ]] || { echo 'Executer avec sudo.'; exit 1; }
[[ $# == 4 ]] || { echo 'Arguments attendus : archive sha256 image-id image-size-bytes'; exit 1; }
archive=$(realpath -e -- "$1")
checksum=$2
new_image=$3
image_bytes=$4
[[ $image_bytes =~ ^[0-9]+$ ]] && (( image_bytes > 0 )) || exit 1
[[ $checksum =~ ^[a-f0-9]{64}$ && $new_image =~ ^sha256:[a-f0-9]{64}$ ]] || exit 1
compose=/home/migration/vmail/compose.yaml
command=/usr/local/bin/docker-compose
payload_dir=$(dirname "$archive")
definition="$payload_dir/compose.gotenberg.yaml"
config_builder="$payload_dir/vps-preview-config.py"
preview_overlay=/home/migration/vmail/compose.vinz-preview.json
preview_service=/home/migration/vmail/compose.gotenberg.yaml
candidate="$payload_dir/preview-candidate.json"
[[ -f $definition && -f $config_builder ]] || { echo 'Fichiers des apercus Office manquants.'; exit 1; }
[[ -f $compose && -x $command ]] || { echo 'Installation Vmail introuvable.'; exit 1; }
exec 9>/var/lock/vmail-update.lock
flock -n 9 || { echo 'Une mise a jour est deja en cours.'; exit 1; }
dc() {
  local files=(-p vmail -f "$compose")
  [[ ! -f $preview_overlay ]] || files+=(-f "$preview_overlay")
  "$command" "${files[@]}" "$@"
}
candidate_dc() { "$command" -p vmail -f "$compose" -f "$candidate" "$@"; }
dc config --quiet
printf '%s  %s\n' "$checksum" "$archive" | sha256sum -c -

# Pin the running image before changing any tag. Keep the previous image.
web=$(dc ps -q php)
worker=$(dc ps -q worker)
[[ -n $web && -n $worker ]] || { echo 'Web ou worker absent : verifier la stack avant la mise a jour.'; exit 1; }
old_image=$(docker inspect "$web" --format '{{.Image}}')
[[ $(docker inspect "$worker" --format '{{.Image}}') == "$old_image" ]] || {
  echo 'Le web et le worker utilisent des images differentes.'; exit 1;
}
[[ $(docker image inspect pl_mail-local:latest --format '{{.Id}}') == "$old_image" ]] || {
  echo 'Le tag de production ne correspond pas au web actif. Verification manuelle requise.'; exit 1;
}
managed=()
if [[ -f $preview_overlay ]]; then
  python3 -c 'import json,sys; sys.exit(0 if json.load(open(sys.argv[1])).get("x-vinz-office-preview") == 1 else 1)' "$preview_overlay" || {
    echo 'Le fichier des apercus existe mais ne porte pas notre marqueur. Verification manuelle requise.'; exit 1;
  }
  managed=(--managed)
fi
dc config --format json | python3 "$config_builder" --definition "$definition" --output "$candidate" "${managed[@]}"
candidate_dc config --quiet
candidate_dc config --format json | python3 -c '
import json,sys
actual=json.load(sys.stdin)
expected=json.load(open(sys.argv[1]))
assert set(expected["services"]["php"]["networks"]) <= set(actual["services"]["php"]["networks"]), "Un reseau du web serait perdu"
got=actual["services"]["gotenberg"]
assert set(got["networks"]) == {"document-preview"}, "Gotenberg doit rester isole"
assert actual["networks"]["document-preview"].get("internal"), "Reseau non interne"
assert not got.get("ports") and not got.get("volumes"), "Gotenberg ne doit publier aucun port ni monter les mails"
' "$candidate"
if [[ $old_image == "$new_image" && -f $preview_service ]] && cmp -s "$definition" "$preview_service"; then
  if cmp -s <(dc config --format json) <(candidate_dc config --format json) &&
     dc exec -T php curl -fsS --max-time 10 http://gotenberg:3000/health >/dev/null 2>&1; then
    echo 'Cette image et les apercus Office sont deja actifs. Aucun changement.'
    rm -- "$archive"
    exit 0
  fi
fi
stamp=$(date -u +%Y%m%d-%H%M%S)
backup="/home/migration/vmail/backups/$stamp"
[[ ! -e $backup ]] || { echo 'Dossier de sauvegarde deja present.'; exit 1; }
mkdir -p "$backup"
chmod 700 "$backup"
printf '%s\n' "$old_image" > "$backup/previous-image.txt"
docker tag "$old_image" "vmail-rollback:$stamp"
cp "$compose" "$backup/compose.yaml"
[[ ! -f $preview_overlay ]] || cp "$preview_overlay" "$backup/compose.vinz-preview.json"
[[ ! -f $preview_service ]] || cp "$preview_service" "$backup/compose.gotenberg.yaml"

# Conservative space estimate for the uncompressed app:backup copy and SQL dump.
storage_kb=$(docker exec "$web" sh -c 'du -sk /app/var/attachments /app/var/raw /app/var/uploads' | awk '{n+=$1} END {print n}')
db_bytes=$(dc exec -T database psql -U app -d app -Atc 'SELECT pg_database_size(current_database());' | tr -d '\r')
[[ $storage_kb =~ ^[0-9]+$ && $db_bytes =~ ^[0-9]+$ ]] || exit 1
available_kb=$(df -Pk "$backup" | awk 'END {print $4}')
# Reserve the entire uncompressed app image plus working headroom, even when
# most Docker layers will be reused. Converter space is only needed on first install.
reserve_kb=$((image_bytes / 1024 + 524288))
(( reserve_kb >= 1572864 )) || reserve_kb=1572864
converter_image=$(candidate_dc config --format json | python3 -c 'import json,sys; print(json.load(sys.stdin)["services"]["gotenberg"]["image"])')
if ! docker image inspect "$converter_image" >/dev/null 2>&1; then
  reserve_kb=$((reserve_kb + 2097152))
fi
required_kb=$((storage_kb + 3 * db_bytes / 1024 + reserve_kb))
printf 'Espace disponible : %s Kio ; estimation sauvegarde et import : %s Kio.\n' "$available_kb" "$required_kb"
(( available_kb > required_kb )) || {
  echo 'Espace disque insuffisant pour une sauvegarde complete. Aucune interruption effectuee.'; exit 1;
}

# Download the converter while the existing mail service is still available.
echo 'Preparation du convertisseur prive Office (limite VPS : 512 Mio)...'
if ! docker image inspect "$converter_image" >/dev/null 2>&1; then
  candidate_dc pull gotenberg
fi

stopped=false
deployment_started=false
on_exit() {
  result=$?
  trap - EXIT
  if (( result != 0 )); then
    echo "ECHEC. Sauvegarde et journaux : $backup"
    if [[ $stopped == true && $deployment_started == false ]]; then
      echo 'Relance de la version precedente ; aucune migration nouvelle executee.'
      dc start php worker || true
    elif [[ $deployment_started == true ]]; then
      echo 'Verifier les journaux. Pas de retour automatique : le schema peut avoir migre.'
      dc logs --tail=60 php worker gotenberg > "$backup/deployment-error.log" 2>&1 || true
    fi
  fi
  exit "$result"
}
trap on_exit EXIT
stopped=true
dc stop php worker
echo "Sauvegarde complete vers $backup ..."
dc run --rm --no-deps -v "$backup:/backup" --entrypoint php php \
  bin/console app:backup /backup --skip-secrets 2>&1 | tee "$backup/backup.log"
if grep -qE 'Could not copy|Cannot create|pg_dump failed' "$backup/backup.log"; then
  echo 'Sauvegarde incomplete : mise a jour annulee.'; exit 1
fi
[[ -s $backup/database.sql ]] || { echo 'Dump vide.'; exit 1; }
# app:backup does not include the JWT files. Archive the complete secrets volume.
docker run --rm --network none --volumes-from "$web" -v "$backup:/backup" \
  --entrypoint sh "$old_image" -c \
  'set -e; test -s /app/var/secrets/generated.env; tar -czf /backup/secrets-volume.tar.gz -C /app/var/secrets .'
[[ -s $backup/secrets-volume.tar.gz ]] || exit 1

echo 'Import de la nouvelle image...'
docker load -i "$archive"
docker image inspect "$new_image" >/dev/null
docker run --rm --network none --entrypoint sh "$new_image" -c \
  'test -f /app/src/Service/Mail/OfficePreviewConverter.php && test -f /app/src/Domain/Helper/OfficeAttachment.php'
deployment_started=true
docker tag "$new_image" pl_mail-local:latest
# Add a dedicated override; never rewrite the VPS base Compose or Traefik settings.
install -m 600 "$definition" "$preview_service"
python3 - "$candidate" "$preview_overlay" "$preview_service" <<'PY'
import json, os, sys
with open(sys.argv[1]) as f:
    data = json.load(f)
data["services"]["gotenberg"]["extends"]["file"] = sys.argv[3]
temporary = sys.argv[2] + ".tmp"
with open(temporary, "w") as f:
    json.dump(data, f, indent=2)
    f.write("\n")
os.replace(temporary, sys.argv[2])
PY
dc config --quiet
dc up -d --no-build --pull never secrets-init gotenberg php worker

echo 'Verification du web et des traitements...'
ready=false
for attempt in $(seq 1 60); do
  if dc exec -T php curl -fsS http://localhost/healthz > "$backup/healthz.json" 2>/dev/null; then
    if python3 - "$backup/healthz.json" <<'PY'
import json, sys
with open(sys.argv[1]) as f:
    data = json.load(f)
checks = data.get('checks', {})
sys.exit(0 if data.get('status') == 'ok' and all(checks.get(k) is True for k in ('database', 'queue', 'workers')) else 1)
PY
    then ready=true; break; fi
  fi
  sleep 3
done
[[ $ready == true ]] || { echo 'Etat de sante non valide apres trois minutes.'; exit 1; }
for service in php worker; do
  container=$(dc ps -q "$service")
  [[ $(docker inspect "$container" --format '{{.Image}}') == "$new_image" ]] || exit 1
done
dc exec -T php php -r 'exit(getenv("GOTENBERG_URL") === "http://gotenberg:3000" ? 0 : 1);'
dc exec -T php curl -fsS --max-time 10 http://gotenberg:3000/health > "$backup/gotenberg-health.json"
echo 'Apercus Office : configuration active et convertisseur accessible depuis le web.'
# Traefik publishes a recreated container only after Docker reports it healthy.
# The internal endpoint can be ready before that first healthcheck (temporary 404).
public_ready=false
for attempt in $(seq 1 30); do
  if curl -fsS --max-time 10 https://vmail.lagratte.net/healthz > "$backup/public-healthz.json" 2>/dev/null; then
    public_ready=true
    break
  fi
  sleep 2
done
[[ $public_ready == true ]] || { echo 'Acces HTTPS public non disponible apres attente du proxy.'; exit 1; }
echo "Mise a jour reussie. Image : $new_image"
echo "Sauvegarde conservee : $backup"
echo 'Les sauvegardes contiennent des secrets : prevoir leur conservation securisee hors VPS.'
# Only this explicitly supplied upload is removed, after successful validation.
rm -- "$archive"
