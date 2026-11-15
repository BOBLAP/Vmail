<?php

declare(strict_types=1);

namespace App\Command\Mail;

use App\Domain\Enum\Mail\SyncTrigger;
use App\Domain\Enum\Mail\SyncFrequency;
use App\Infrastructure\Messaging\Message\SyncAccountMessage;
use App\Repository\Mail\AccountRepository;
use App\Service\Demo\DemoMode;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:mail:sync',
    description: 'Dispatch an account-level sync (IMAP or Gmail) for one or all active accounts',
)]
final class MailSyncCommand extends Command
{
    public function __construct(
        private readonly AccountRepository   $accountRepository,
        private readonly MessageBusInterface $bus,
        private readonly DemoMode            $demoMode,
        private readonly CacheItemPoolInterface $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('account-id', InputArgument::OPTIONAL, 'Sync a single account by ID; omit for all active accounts');
        $this->addOption('due', null, InputOption::VALUE_NONE, 'Only dispatch accounts due for their scheduled poll');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);

        // A demo instance has no real mailbox to reach: its accounts point at
        // documentation domains, so every dispatch here would be a worker
        // spending its full retry ladder on a host that does not resolve.
        // Refused outright rather than left to fail, because a queue full of
        // failures is precisely the state the admin panel exists to make
        // alarming — and on a demo it would be alarming about nothing.
        //
        // This branch also keeps the scheduled sweep quiet on demo instances.
        if (true === $this->demoMode->isEnabled()) {
            $io->note('Demo mode is on — mail sync is disabled.');

            return Command::SUCCESS;
        }

        $accountId = $input->getArgument('account-id');

        if (null !== $accountId) {
            $account = $this->accountRepository->find((int) $accountId);

            if (null === $account) {
                $io->error(sprintf('Account %s not found.', $accountId));
                return Command::FAILURE;
            }

            $accounts = [$account];
        } else {
            $accounts = $this->accountRepository->findBy(['isActive' => true]);
        }

        $dueOnly = (bool) $input->getOption('due');
        $minute = intdiv(time(), 60);
        $dispatched = 0;
        foreach ($accounts as $account) {
            $lastPoll = null;
            if ($dueOnly) {
                $frequency = SyncFrequency::forAccount($account);
                $lastPoll = $this->cache->getItem('mail.last_poll.' . $account->id);
                $previous = $lastPoll->get();
                if (is_int($previous) && $minute - $previous < $frequency->minutes($account->usr)) {
                    continue;
                }
            }

            $this->bus->dispatch(new SyncAccountMessage($account->id, SyncTrigger::Poll));
            if (null !== $lastPoll) {
                $this->cache->save($lastPoll->set($minute)->expiresAfter(86400));
            }
            ++$dispatched;
            $io->text(sprintf('→ dispatched sync for %s (#%d)', $account->email, $account->id));
        }

        $io->success(sprintf('Dispatched %d account sync(s).', $dispatched));

        return Command::SUCCESS;
    }
}
