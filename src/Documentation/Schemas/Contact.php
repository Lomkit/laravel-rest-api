<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Contact extends Schema
{
    /**
     * The identifying name of the contact person/organization.
     * @var string
     */
    protected string $name;

    /**
     * The URL pointing to the contact information.
     * @var string
     */
    protected string $url;

    /**
     * The email address of the contact person/organization.
     * @var string
     */
    protected string $email;

    public function withName(string $name): Contact
    {
        $this->name = $name;
        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withUrl(string $url): Contact
    {
        $this->url = $url;
        return $this;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function withEmail(string $email): Contact
    {
        $this->email = $email;
        return $this;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name(),
            'url' => $this->url(),
            'email' => $this->email()
        ];
    }

    public function generate(): Contact
    {
        return $this
            ->withName(config('rest.documentation.info.contact.name'))
            ->withEmail(config('rest.documentation.info.contact.email'))
            ->withUrl(config('rest.documentation.info.contact.url'));
    }
}