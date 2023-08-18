<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Info extends Schema
{
    /**
     * The title of the API.
     * @var string
     */
    protected string $title;

    /**
     * A short summary of the API.
     * @var string
     */
    protected string $summary;

    /**
     * A description of the API. CommonMark syntax MAY be used for rich text representation.
     * @var string
     */
    protected string $description;

    /**
     * A URL to the Terms of Service for the API.
     * @var string|null
     */
    protected string|null $termsOfService;

    /**
     * The contact information for the exposed API.
     * @var Contact
     */
    protected Contact $contact;

    /**
     * The license information for the exposed API.
     * @var License
     */
    protected License $license;

    /**
     * The version of the OpenAPI document
     * @var string
     */
    protected string $version;

    public function withTitle(string $title): Info
    {
        $this->title = $title;
        return $this;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function withSummary(string $summary): Info
    {
        $this->summary = $summary;
        return $this;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function withDescription(string $description): Info
    {
        $this->description = $description;
        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function withTermsOfService(string|null $termsOfService): Info
    {
        $this->termsOfService = $termsOfService;
        return $this;
    }

    public function termsOfService(): string|null
    {
        return $this->termsOfService;
    }

    public function withContact(Contact $contact): Info
    {
        $this->contact = $contact;
        return $this;
    }

    public function contact(): Contact
    {
        return $this->contact;
    }

    public function withLicense(License $license): Info
    {
        $this->license = $license;
        return $this;
    }

    public function license(): License
    {
        return $this->license;
    }

    public function withVersion(string $version): Info
    {
        $this->version = $version;
        return $this;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(
            [
                'title' => $this->title(),
                'summary' => $this->summary(),
                'description' => $this->description(),
                'contact' => $this->contact()->jsonSerialize(),
                'license' => $this->license()->jsonSerialize(),
                'version' => $this->version()
            ],
            !is_null($this->termsOfService()) ? ['termsOfService' => $this->termsOfService()] : []
        );
    }
}