<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\NewsletterRecipient;
use QETG\Domain\Repository\NewsletterRecipientRepositoryInterface;

final class MySqlNewsletterRecipientRepository extends MySqlRepository implements NewsletterRecipientRepositoryInterface
{
    public function findSubscribedRecipients(): array
    {
        $rows = $this->many('SELECT id, login, email FROM quiz_membre WHERE newletter = 1 ORDER BY id');
        return array_map(
            static fn (array $row): NewsletterRecipient => new NewsletterRecipient(
                (int) $row['id'],
                (string) $row['login'],
                (string) $row['email']
            ),
            $rows
        );
    }
}
