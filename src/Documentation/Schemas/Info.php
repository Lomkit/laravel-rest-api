<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Info extends Schema
{
    /**
     * The title of the API.
     */
    protected string $title;

    /**
     * A short summary of the API.
     */
    protected string $summary;

    /**
     * A description of the API. CommonMark syntax MAY be used for rich text representation.
     */
    protected string $description;

    /**
     * A URL to the Terms of Service for the API.
     */
    protected ?string $termsOfService;

    /**
     * The contact information for the exposed API.
     */
    protected Contact $contact;

    /**
     * The license information for the exposed API.
     */
    protected License $license;

    /**
     * The version of the OpenAPI document.
     */
    protected string $version;

    /**
     * Set the title for the API.
     */
    public function withTitle(string $title): Info
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the title of the API.
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Set a short summary of the API.
     */
    public function withSummary(string $summary): Info
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * Get a short summary of the API.
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * Set a description of the API.
     */
    public function withDescription(string $description): Info
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get a description of the API.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the URL to the Terms of Service for the API.
     */
    public function withTermsOfService(?string $termsOfService): Info
    {
        $this->termsOfService = $termsOfService;

        return $this;
    }

    /**
     * Get the URL to the Terms of Service for the API.
     */
    public function termsOfService(): ?string
    {
        return $this->termsOfService;
    }

    /**
     * Set the contact information for the exposed API.
     */
    public function withContact(Contact $contact): Info
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * Get the contact information for the exposed API.
     */
    public function contact(): Contact
    {
        return $this->contact;
    }

    /**
     * Set the license information for the exposed API.
     */
    public function withLicense(License $license): Info
    {
        $this->license = $license;

        return $this;
    }

    /**
     * Get the license information for the exposed API.
     */
    public function license(): License
    {
        return $this->license;
    }

    /**
     * Set the version of the OpenAPI document.
     */
    public function withVersion(string $version): Info
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get the version of the OpenAPI document.
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * Serialize the object to a JSON representation.
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            [
                'title' => $this->title(),
                'summary' => $this->summary(),
                'description' => $this->description(),
                'contact' => $this->contact()->jsonSerialize(),
                'license' => $this->license()->jsonSerialize(),
                'version' => $this->version(),
            ],
            ! is_null($this->termsOfService()) ? ['termsOfService' => $this->termsOfService()] : []
        );
    }

    /**
     * Generate an Info object with default values.
     */
    public function generate(): Info
    {
        return $this
            ->withTitle(config('rest.documentation.info.title'))
            ->withSummary(config('rest.documentation.info.summary'))
            ->withDescription(config('rest.documentation.info.description'))
            ->withTermsOfService(config('rest.documentation.info.termsOfService'))
            ->withContact(
                (new Contact)
                    ->generate()
            )
            ->withLicense(
                (new License)
                    ->generate()
            )
            ->withVersion(config('rest.documentation.info.version'));
    }
}
