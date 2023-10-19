<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
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

    /**
     * Handle the console command.
     *
     * @return void
     */
    public function handle()
    {
        $openApi = (new OpenAPI())
            ->generate();

        $path = $this->getPath('openapi');

        $this->makeDirectory($path);

        $this->files->put(
            $path,
            json_encode($openApi->jsonSerialize())
        );

        $this->info('The documentation was generated successfully!');
        $this->info('Open '.url(config('rest.documentation.routing.path')). 'in a web browser.');
    }

    /**
     * Get the documentation path.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getPath($name)
    {
        return !is_null($this->option('path')) ? $this->option('path').'/'.$name.'.json' : public_path('vendor/rest/'.$name.'.json');
    }

    /**
     * Get the stub file for the generator.
     *
     * @throws RuntimeException
     *
     * @return string
     */
    protected function getStub()
    {
        throw new RuntimeException('Should not be here');
    }
}
