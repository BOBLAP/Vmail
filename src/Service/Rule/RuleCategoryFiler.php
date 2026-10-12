<?php

declare(strict_types=1);

namespace App\Service\Rule;

use App\Domain\Enum\Mail\SenderVerdict;
use App\Entity\Mail\Message;
use App\Entity\Rule\MailRule;
use App\Repository\Mail\InboxCategoryRepository;
use App\Service\Imap\MessageThreader;
use App\Service\Mail\SenderAuthentication;
use Psr\Log\LoggerInterface;

/**
 * Files a message under one of the reader's own categories, when a filter's
 * "File in category" action says to — and refuses when filing it would put
 * something where it must not be.
 *
 * THE REFUSALS ARE THE POINT
 * ──────────────────────────
 * A filter names a sender, and a sender is a line anybody can write. A reader
 * who builds a "Shipping" tab from "sender's domain is mondialrelay.fr" has
 * made somewhere that parcel-delivery phishing, sent from a forged
 * mondialrelay.fr, would sit beside the real notices looking exactly as
 * legitimate as they do. The only thing that tells the two apart is the
 * receiving server's authentication verdict, so this files a message only when
 * SenderAuthentication says Pass. Not Fail, and — a decision, not an accident —
 * not Unknown: an account whose receiving server plMail cannot identify files
 * nothing, because "we could not tell" is exactly what a forgery relies on. The
 * cost is that such an account cannot use the feature; docs/features/filters.md
 * says so. What this does NOT cover is a Pass that a believed server gave
 * wrongly — see SenderAuthentication for whose word counts.
 *
 * What a refusal does is nothing: the message is left to the ordinary rules,
 * which is where it would have been had no filter matched. Filing only ever
 * chooses a tab — it never touches labels, the Spam folder, read state or the
 * authentication warning the message view shows.
 *
 * Gmail's own category is respected the way the categoriser respects it: a
 * message carrying Gmail's labels is filed only when the reader asked plMail's
 * sorting to overrule the provider's (CategorySorting::$overrideProvider). The
 * test is the labels on the row, not the account's type, because that is what
 * the categoriser keys on — a Gmailified IMAP row carries them too.
 *
 * Two filters matching one message: the one that runs last decides, because the
 * column is single-valued and this overwrites. "Stop after this filter" is the
 * existing way to make an earlier match final.
 */
final readonly class RuleCategoryFiler
{
    public function __construct(
        private InboxCategoryRepository $categories,
        private SenderAuthentication    $authentication,
        private MessageThreader         $threader,
        private LoggerInterface         $logger,
    ) {}

    /**
     * @param array<string,mixed> $action the stored {type, categoryId} entry
     *
     * @return bool whether the message was filed
     */
    public function file(MailRule $rule, Message $message, array $action): bool
    {
        $categoryId = $action['categoryId'] ?? null;

        if (false === is_int($categoryId)) {
            return false;
        }

        $category = $this->categories->find($categoryId);

        // Re-checked at run time, not trusted from the stored action: whatever
        // is in the json, a rule must never reach a category its owner does not
        // own, and a deleted one is simply nothing to file under. The same
        // discipline as labels and integrations in RuleActionExecutor.
        if (null === $category || null === $rule->usr || $category->usr !== $rule->usr) {
            $this->logger->warning('RuleCategoryFiler: category not found for rule owner', [
                'ruleId'     => $rule->id,
                'categoryId' => $categoryId,
            ]);

            return false;
        }

        if (null !== $message->gmailLabelIds && false === $rule->usr->categorySorting->overrideProvider) {
            return false;
        }

        if (SenderVerdict::Pass !== $this->authentication->verdict($message)) {
            return false;
        }

        $message->ruleCategory = $category;

        $thread = $message->thread;

        if (null !== $thread) {
            // The same most-recent-wins, pin and hold rules the built-in
            // category follows, rather than a second copy of them here.
            $this->threader->refreshCategory($message, $thread);
        }

        return true;
    }
}
