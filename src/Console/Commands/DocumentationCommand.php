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
        $openApi = (new OpenAPI)
            ->generate();

        $path = $this->getPath('openapi');

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

        return !is_null($this->option('path')) ? $this->option('path').'/'.$name.'.json' : public_path('vendor/rest/'.$name.'.json');
    }

    protected function getStub()
    {
        throw new RuntimeException('Should not be here');
    }
}
