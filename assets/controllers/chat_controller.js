import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "conversation",
        "title",
        "messages",
        "composer",
        "input"
    ];

    static values = {
        view: {
            type: String,
            default: "list"
        }
    };

    connect() {
        this.selectedConversationId = null;
    }

    open(event) {
        const conversationId = event.params.conversationId;
        const conversationName = event.params.conversationName;

        this.selectedConversationId = conversationId;
        this.viewValue = "conversation";

        this.titleTarget.textContent = conversationName;

        this.conversationTargets.forEach((conversation) => {
            const isActive =
                conversation.dataset.chatConversationIdParam ===
                String(conversationId);

            conversation.classList.toggle("is-active", isActive);

            conversation.setAttribute(
                "aria-current",
                isActive ? "true" : "false"
            );
        });

        /* load messages with fetch() to add.
         */

        this.dispatch("conversation-opened", {
            detail: {
                conversationId,
                conversationName
            }
        });
    }

    back() {
        this.viewValue = "list";
    }

    send(event) {
        event.preventDefault();

        /*
         * Send fetch() to add.
         */
    }
}