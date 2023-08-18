<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\ResolvesStubPath;
use Lomkit\Rest\Documentation\Schemas\Contact;
use Lomkit\Rest\Documentation\Schemas\Info;
use Lomkit\Rest\Documentation\Schemas\License;
use Lomkit\Rest\Documentation\Schemas\OpenAPI;
use RuntimeException;

class DocumentationCommand extends GeneratorCommand implements PromptsForMissingInput
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'rest:documentation
        {--path= : The location where the documentation file should be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the documentation';

    public function handle()
    {
        $openApi = $this->generateOpenApiSchema();

        $path = $this->getPath('open-api');

        $this->makeDirectory($path);

        $this->files->put(
            $path,
            json_encode($openApi->jsonSerialize())
        );
    }

    /**
     * Get the documentation path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        return storage_path('app/documentation/'.$name.'.json');
    }



    protected function generateOpenApiSchema(): OpenAPI
    {
        return (new OpenAPI)
            ->withInfo(
                $this->generateInfoSchema()
            )
            ->withPaths([])
            ->withSecurity([])
            ->withServers([]);
    }

    protected function generateInfoSchema(): Info
    {
        return (new Info)
            ->withTitle(config('rest.documentation.info.title'))
            ->withSummary(config('rest.documentation.info.summary'))
            ->withDescription(config('rest.documentation.info.description'))
            ->withTermsOfService(config('rest.documentation.info.termsOfService'))
            ->withContact(
                $this->generateContactSchema()
            )
            ->withLicense(
                $this->generateLicense()
            )
            ->withVersion(config('rest.documentation.info.version'));
    }

    protected function generateContactSchema(): Contact
    {
        return (new Contact)
            ->withName(config('rest.documentation.info.contact.name'))
            ->withEmail(config('rest.documentation.info.contact.email'))
            ->withUrl(config('rest.documentation.info.contact.url'));
    }

    protected function generateLicense(): License
    {
        return (new License)
            ->withUrl(config('rest.documentation.info.license.url'))
            ->withName(config('rest.documentation.info.license.name'))
            ->withIdentifier(config('rest.documentation.info.license.identifier'));
    }

    protected function getStub()
    {
        throw new RuntimeException('Should not be here');
    }
}
