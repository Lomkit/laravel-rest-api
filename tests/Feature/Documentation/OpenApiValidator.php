<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final readonly class OpenApiValidator
{
    public const string DOCUMENT_SCHEMA = 'https://spec.openapis.org/oas/3.1/schema/2025-11-23';

    private const string DOCUMENT = 'https://openapi.lomkit.test/document.json';

    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator;
        $this->validator->setMaxErrors(10);
        $this->validator->parser()->setOption('allowDefaults', false);

        foreach (glob(__DIR__.'/MetaSchemas/*.json') ?: [] as $file) {
            $schema = json_decode((string) file_get_contents($file), flags: JSON_THROW_ON_ERROR);
            $this->validator->resolver()?->registerRaw($schema, $schema->{'$id'});
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, list<string>>
     */
    public function documentErrors(array $document): array
    {
        return $this->errors(self::toObject($document), self::DOCUMENT_SCHEMA);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, list<string>>
     */
    public function responseErrors(array $document, string $path, string $method, int $status, string $body): array
    {
        $pointer = implode('/', ['#', 'paths', self::escape($path), $method, 'responses', (string) $status, 'content', 'application~1json', 'schema']);

        return $this->schemaErrors($document, $pointer, json_decode($body, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, list<string>>
     */
    public function schemaErrors(array $document, string $pointer, mixed $data): array
    {
        $this->validator->resolver()?->unregisterRaw(self::DOCUMENT);
        $this->validator->resolver()?->registerRaw(self::toObject($document), self::DOCUMENT);

        return $this->errors(self::toObject($data), self::DOCUMENT.$pointer);
    }

    /** @return array<string, list<string>> */
    private function errors(mixed $data, string $schema): array
    {
        $result = $this->validator->validate($data, $schema);

        if ($result->isValid()) {
            return [];
        }

        /** @var array<string, list<string>> */
        return (new ErrorFormatter)->format($result->error());
    }

    private static function escape(string $path): string
    {
        return rawurlencode(str_replace(['~', '/'], ['~0', '~1'], $path));
    }

    private static function toObject(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    }
}
