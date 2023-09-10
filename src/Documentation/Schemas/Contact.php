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
     *
     * @param  string  $url
     * @return Contact
     */
    public function withUrl(string $url): Contact
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get the URL pointing to the contact information.
     *
     * @return string
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Set the email address of the contact person/organization.
     *
     * @param  string  $email
     * @return Contact
     */
    public function withEmail(string $email): Contact
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get the email address of the contact person/organization.
     *
     * @return string
     */
    public function email(): string
    {
        return $this->email;
    }

    /**
     * Serialize the contact information as an array.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name(),
            'url' => $this->url(),
            'email' => $this->email()
        ];
    }

    /**
     * Generate the contact information using configuration values.
     *
     * @return Contact
     */
    public function generate(): Contact
    {
        return $this
            ->withName(config('rest.documentation.info.contact.name'))
            ->withEmail(config('rest.documentation.info.contact.email'))
            ->withUrl(config('rest.documentation.info.contact.url'));
    }
}