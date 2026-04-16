<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Core\Content\FormApiData;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FormApiDataEntity extends Entity
{
    use EntityIdTrait;

    /** @var array<int, mixed>|null  /api/v2/agents */
    protected ?array $agents = null;

    /** @var array<int, mixed>|null  /api/v2/email_configs */
    protected ?array $emailConfigs = null;

    /** @var array<int, mixed>|null  /api/v2/companies */
    protected ?array $companies = null;

    /** @var array<int, mixed>|null  /api/v2/ticket_fields */
    protected ?array $ticketFields = null;

    /** @var array<int, mixed>|null  /api/v2/groups */
    protected ?array $groups = null;

    /** @var array<int, mixed>|null  /api/v2/products */
    protected ?array $products = null;

    // ── agents ───────────────────────────────────────────────────────────────

    /** @return array<int, mixed>|null */
    public function getAgents(): ?array { return $this->agents; }

    /** @param array<int, mixed>|null $agents */
    public function setAgents(?array $agents): void { $this->agents = $agents; }

    // ── emailConfigs ─────────────────────────────────────────────────────────

    /** @return array<int, mixed>|null */
    public function getEmailConfigs(): ?array { return $this->emailConfigs; }

    /** @param array<int, mixed>|null $emailConfigs */
    public function setEmailConfigs(?array $emailConfigs): void { $this->emailConfigs = $emailConfigs; }

    // ── companies ────────────────────────────────────────────────────────────

    /** @return array<int, mixed>|null */
    public function getCompanies(): ?array { return $this->companies; }

    /** @param array<int, mixed>|null $companies */
    public function setCompanies(?array $companies): void { $this->companies = $companies; }

    // ── ticketFields ─────────────────────────────────────────────────────────

    /** @return array<int, mixed>|null */
    public function getTicketFields(): ?array { return $this->ticketFields; }

    /** @param array<int, mixed>|null $ticketFields */
    public function setTicketFields(?array $ticketFields): void { $this->ticketFields = $ticketFields; }

    // ── groups ───────────────────────────────────────────────────────────────

    /** @return array<int, mixed>|null */
    public function getGroups(): ?array { return $this->groups; }

    /** @param array<int, mixed>|null $groups */
    public function setGroups(?array $groups): void { $this->groups = $groups; }

    // ── products ─────────────────────────────────────────────────────────────

    /** @return array<int, mixed>|null */
    public function getProducts(): ?array { return $this->products; }

    /** @param array<int, mixed>|null $products */
    public function setProducts(?array $products): void { $this->products = $products; }
}
