import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["list"];

    start(event) {
        this.dragged = event.currentTarget.closest("li");
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", "");
    }

    end() {
        this.dragged = null;
    }

    allowDrop(event) {
        if (!this.dragged) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
    }

    drop(event) {
        if (!this.dragged) return;
        event.preventDefault();
        this.move(this.dragged, event.currentTarget.dataset.syncGroup);
        this.end();
    }

    choose(event) {
        const select = event.currentTarget;
        this.move(select.closest("li"), select.value);
        select.focus();
    }

    move(account, group) {
        const list = this.listTargets.find(candidate => candidate.dataset.group === group);
        if (!list || account.parentElement === list) return;
        account.querySelector("select").value = group;
        list.append(account);
    }
}
