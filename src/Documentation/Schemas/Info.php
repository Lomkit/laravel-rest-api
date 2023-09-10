<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Info extends Schema
{
    /**
     * The title of the API.
     *
     * @var string
     */
    protected string $title;

    /**
     * A short summary of the API.
     *
     * @var string
     */
    protected string $summary;

    /**
     * A description of the API. CommonMark syntax MAY be used for rich text representation.
     *
     * @var string
     */
    protected string $description;

    /**
     * A URL to the Terms of Service for the API.
     *
     * @var string|null
     */
    protected string|null $termsOfService;

    /**
     * The contact information for the exposed API.
     *
     * @var Contact
     */
    protected Contact $contact;

    /**
     * The license information for the exposed API.
     *
     * @var License
     */
    protected License $license;

    /**
     * The version of the OpenAPI document.
     *
     * @var string
     */
    protected string $version;

    /**
     * Set the title for the API.
     *
     * @param string $title
     *
     * @return Info
     */
    public function withTitle(string $title): Info
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the title of the API.
     *
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Set a short summary of the API.
     *
     * @param string $summary
     *
     * @return Info
     */
    public function withSummary(string $summary): Info
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * Get a short summary of the API.
     *
     * @return string
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * Set a description of the API.
     *
     * @param string $description
     *
     * @return Info
     */
    public function withDescription(string $description): Info
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get a description of the API.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the URL to the Terms of Service for the API.
     *
     * @param string|null $termsOfService
     *
     * @return Info
     */
    public function withTermsOfService(string|null $termsOfService): Info
    {
        $this->termsOfService = $termsOfService;

        return $this;
    }

    /**
     * Get the URL to the Terms of Service for the API.
     *
     * @return string|null
     */
    public function termsOfService(): string|null
    {
        return $this->termsOfService;
    }

    /**
     * Set the contact information for the exposed API.
     *
     * @param Contact $contact
     *
     * @return Info
     */
    public function withContact(Contact $contact): Info
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * Get the contact information for the exposed API.
     *
     * @return Contact
     */
    public function contact(): Contact
    {
        return $this->contact;
    }

    /**
     * Set the license information for the exposed API.
     *
     * @param License $license
     *
     * @return Info
     */
    public function withLicense(License $license): Info
    {
        $this->license = $license;

        return $this;
    }

    /**
     * Get the license information for the exposed API.
     *
     * @return License
     */
    public function license(): License
    {
        return $this->license;
    }

    /**
     * Set the version of the OpenAPI document.
     *
     * @param string $version
     *
     * @return Info
     */
    public function withVersion(string $version): Info
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get the version of the OpenAPI document.
     *
     * @return string
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * Serialize the object to a JSON representation.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            [
                'title'       => $this->title(),
                'summary'     => $this->summary(),
                'description' => $this->description(),
                'contact'     => $this->contact()->jsonSerialize(),
                'license'     => $this->license()->jsonSerialize(),
                'version'     => $this->version(),
            ],
            !is_null($this->termsOfService()) ? ['termsOfService' => $this->termsOfService()] : []
        );
    }

    /**
     * Generate an Info object with default values.
     *
     * @return Info
     */
    public function generate(): Info
    {
        return $this
            ->withTitle(config('rest.documentation.info.title'))
            ->withSummary(config('rest.documentation.info.summary'))
            ->withDescription(config('rest.documentation.info.description'))
            ->withTermsOfService(config('rest.documentation.info.termsOfService'))
            ->withContact(
                (new Contact())
                    ->generate()
            )
            ->withLicense(
                (new License())
                    ->generate()
            )
            ->withVersion(config('rest.documentation.info.version'));
    }
}
