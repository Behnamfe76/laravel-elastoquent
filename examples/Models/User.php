<?php

namespace Fereydooni\LaravelElastoquent\Examples\Models;

use Fereydooni\LaravelElastoquent\Models\Model;
use Fereydooni\LaravelElastoquent\Attributes\Elasticsearch;
use Fereydooni\LaravelElastoquent\Attributes\ElasticsearchField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticsearchId;
use Fereydooni\LaravelElastoquent\Attributes\ElasticsearchIndex;
use Fereydooni\LaravelElastoquent\Attributes\ElasticsearchType;
use Fereydooni\LaravelElastoquent\Attributes\ElasticsearchMapping;
use Fereydooni\LaravelElastoquent\Attributes\ElasticsearchSettings;
use Fereydooni\LaravelElastoquent\Enums\ElasticsearchFieldType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;

class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $age,
        #[MapInputName('is_active')]
        #[MapOutputName('is_active')]
        public bool $isActive,
        public array $roles,
        public array $profile,
        #[MapInputName('created_at')]
        #[MapOutputName('created_at')]
        public \DateTime $createdAt,
        #[MapInputName('updated_at')]
        #[MapOutputName('updated_at')]
        public \DateTime $updatedAt
    ) {}
}

#[Elasticsearch]
#[ElasticsearchIndex('users')]
#[ElasticsearchType('_doc')]
#[ElasticsearchMapping([
    'properties' => [
        'id' => ['type' => 'keyword'],
        'name' => ['type' => 'text'],
        'email' => ['type' => 'keyword'],
        'password' => ['type' => 'keyword'],
        'age' => ['type' => 'integer'],
        'is_active' => ['type' => 'boolean'],
        'created_at' => ['type' => 'date'],
        'updated_at' => ['type' => 'date'],
        'roles' => ['type' => 'keyword'],
        'profile' => [
            'type' => 'nested',
            'properties' => [
                'bio' => ['type' => 'text'],
                'location' => ['type' => 'keyword'],
                'website' => ['type' => 'keyword'],
                'avatar' => ['type' => 'keyword']
            ]
        ]
    ]
])]
#[ElasticsearchSettings([
    'number_of_shards' => 1,
    'number_of_replicas' => 1
])]
class User extends Model
{
    #[ElasticsearchId]
    protected ?string $id = null;

    #[ElasticsearchField(type: ElasticsearchFieldType::TEXT)]
    protected string $name;

    #[ElasticsearchField(type: ElasticsearchFieldType::KEYWORD)]
    protected string $email;

    #[ElasticsearchField(type: ElasticsearchFieldType::KEYWORD)]
    protected string $password;

    #[ElasticsearchField(type: ElasticsearchFieldType::INTEGER)]
    protected int $age;

    #[ElasticsearchField(type: ElasticsearchFieldType::BOOLEAN)]
    protected bool $isActive;

    #[ElasticsearchField(type: ElasticsearchFieldType::DATE)]
    protected \DateTime $createdAt;

    #[ElasticsearchField(type: ElasticsearchFieldType::DATE)]
    protected \DateTime $updatedAt;

    #[ElasticsearchField(type: ElasticsearchFieldType::KEYWORD)]
    protected array $roles = [];

    #[ElasticsearchField(type: ElasticsearchFieldType::NESTED)]
    protected array $profile = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        $this->age = $age;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getProfile(): array
    {
        return $this->profile;
    }

    public function setProfile(array $profile): void
    {
        $this->profile = $profile;
    }

    public function toData(): UserData
    {
        return new UserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            age: $this->age,
            isActive: $this->isActive,
            roles: $this->roles,
            profile: $this->profile,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }
} 