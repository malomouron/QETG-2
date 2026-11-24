<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\NewsletterRecipient;

interface NewsletterRecipientRepositoryInterface
{
    /** @return list<NewsletterRecipient> */
    public function findSubscribedRecipients(): array;
}
