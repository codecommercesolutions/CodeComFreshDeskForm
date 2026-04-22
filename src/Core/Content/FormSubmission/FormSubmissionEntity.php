<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Core\Content\FormSubmission;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FormSubmissionEntity extends Entity
{
    use EntityIdTrait;
    protected string $firstName = '';
    protected string $lastName = '';
    protected string $email = '';
    protected string $phone = '';
    protected string $subject = '';
    protected string $message = '';
    protected ?string $type = null;
    protected ?string $groupId = null;
    protected ?string $productId = null;
    protected ?int $status = null;
    protected ?int $source = null;
    protected ?int $priority = null;
    protected ?string $responderId = null;
    protected ?string $emailConfigId = null;
    protected ?string $companyId = null;
    protected ?string $freshdeskTicketId = null;
    // ── Identity fields ───────────────────────────────────────────────────────
    protected ?string $requesterId = null;
    protected ?string $facebookId = null;
    protected ?string $twitterId = null;
    protected ?string $uniqueExternalId = null;

    /** @var array<string, mixed>|null  Custom Freshdesk ticket field values (default=false) */
    protected ?array $extraFields = null;

    public function getExtraFields(): ?array { return $this->extraFields; }
    public function setExtraFields(?array $v): void { $this->extraFields = $v; }

    public function getResponderId(): ?string
    {
        return $this->responderId;
    }
    public function setResponderId(?string $responderId): void
    {
        $this->responderId = $responderId;
    }

    public function getEmailConfigId(): ?string
    {
        return $this->emailConfigId;
    }
    public function setEmailConfigId(?string $emailConfigId): void
    {
        $this->emailConfigId = $emailConfigId;
    }

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }
    public function setCompanyId(?string $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }
    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }
    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }
    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }


    public function getMessage(): string
    {
        return $this->message;
    }
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getGroupId(): ?string
    {
        return $this->groupId;
    }
    public function setGroupId(?string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }
    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }
    public function setStatus(?int $status): void
    {
        $this->status = $status;
    }

    public function getSource(): ?int
    {
        return $this->source;
    }
    public function setSource(?int $source): void
    {
        $this->source = $source;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }
    public function setPriority(?int $priority): void
    {
        $this->priority = $priority;
    }

    public function getFreshdeskTicketId(): ?string
    {
        return $this->freshdeskTicketId;
    }
    public function setFreshdeskTicketId(?string $freshdeskTicketId): void
    {
        $this->freshdeskTicketId = $freshdeskTicketId;
    }

    public function getRequesterId(): ?string
    {
        return $this->requesterId;
    }
    public function setRequesterId(?string $requesterId): void
    {
        $this->requesterId = $requesterId;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }
    public function setFacebookId(?string $facebookId): void
    {
        $this->facebookId = $facebookId;
    }

    public function getTwitterId(): ?string
    {
        return $this->twitterId;
    }
    public function setTwitterId(?string $twitterId): void
    {
        $this->twitterId = $twitterId;
    }

    public function getUniqueExternalId(): ?string
    {
        return $this->uniqueExternalId;
    }
    public function setUniqueExternalId(?string $uniqueExternalId): void
    {
        $this->uniqueExternalId = $uniqueExternalId;
    }
}
