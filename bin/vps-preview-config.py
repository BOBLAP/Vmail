#!/usr/bin/env python3
"""Generate a minimal VPS override, preserving the web service's existing networks."""
import argparse
import json
from pathlib import Path
import sys


def preview_override(base, definition, managed=False):
    services = base.get("services", {})
    web = services.get("php", {})
    if not web or web.get("network_mode"):
        raise ValueError("The web service must use Compose networks.")
    if "gotenberg" in services and not managed:
        raise ValueError("An unmanaged Gotenberg service already exists; refusing to replace it.")
    existing = base.get("networks", {}).get("document-preview")
    if existing and not existing.get("internal", False):
        raise ValueError("The existing document-preview network is not internal.")
    networks = web.get("networks") or {"default": None}
    if isinstance(networks, list):
        networks = dict.fromkeys(networks)
    networks = dict(networks)
    networks["document-preview"] = None
    return {
        "x-vinz-office-preview": 1,
        "services": {
            "gotenberg": {
                "extends": {"file": str(definition), "service": "gotenberg"},
                # This VPS has 2 GiB RAM and also hosts other services.
                "mem_limit": "512m",
                "cpus": 0.5,
                "labels": {"traefik.enable": "false"},
            },
            "php": {
                "environment": {"GOTENBERG_URL": "http://gotenberg:3000"},
                "networks": networks,
                "depends_on": {"gotenberg": {"condition": "service_healthy"}},
            },
        },
        "networks": {"document-preview": {"internal": True}},
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--definition", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--managed", action="store_true")
    args = parser.parse_args()
    try:
        result = preview_override(json.load(sys.stdin), args.definition, args.managed)
        Path(args.output).write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    except (ValueError, OSError) as error:
        sys.exit(str(error))
