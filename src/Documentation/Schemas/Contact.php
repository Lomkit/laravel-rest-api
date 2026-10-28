<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Contact extends Schema
{
    /**
     * The identifying name of the contact person/organization.
     */
    protected string $name;

    /**
     * The URL pointing to the contact information.
     */
    protected string $url;

    /**
     * The email address of the contact person/organization.
     */
    protected string $email;

    public function withName(string $name): Contact
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the identifying name of the contact person/organization.
     *
     * @param  string  $name
     * @return Contact
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set the URL pointing to the contact information.
     */
    public function withUrl(string $url): Contact
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the URL pointing to the contact information.
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Set the email address of the contact person/organization.
     */
    public function withEmail(string $email): Contact
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get the email address of the contact person/organization.
     */
    public function email(): string
    {
        return $this->email;
    }

    /**
     * Serialize the contact information as an array.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name(),
            'url' => $this->url(),
            'email' => $this->email(),
        ];
    }

    /**
     * Generate the contact information using configuration values.
     */
    public function generate(): Contact
    {
        return $this
            ->withName(config('rest.documentation.info.contact.name'))
            ->withEmail(config('rest.documentation.info.contact.email'))
            ->withUrl(config('rest.documentation.info.contact.url'));
    }
}
