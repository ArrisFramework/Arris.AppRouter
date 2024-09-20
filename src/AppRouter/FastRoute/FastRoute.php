<?php
declare(strict_types=1);

namespace Arris\AppRouter\FastRoute;

use Closure;

/** @phpstan-import-type ProcessedData from ConfigureRoutes */
final class FastRoute
{
    /** @var ProcessedData|null */
    public ?array $processedConfiguration = null;

    public Closure $routeDefinitionCallback;
    public string $routeParser;
    public string $dataGenerator;
    public string $dispatcher;

    /**
     * @var string
     */
    public string $routesConfiguration;
    public string $uriGenerator;

    /**
     * @param Closure(ConfigureRoutes):void  $routeDefinitionCallback
     * @param class-string<RouteParser>      $routeParser
     * @param class-string<DataGenerator>    $dataGenerator
     * @param class-string<Dispatcher>       $dispatcher
     * @param class-string<ConfigureRoutes>  $routesConfiguration
     * @param class-string<GenerateUri>      $uriGenerator
     */
    private function __construct(
        Closure $routeDefinitionCallback,
        string $routeParser,
        string $dataGenerator,
        string $dispatcher,
        string $routesConfiguration,
        string $uriGenerator
    ) {
        $this->routeDefinitionCallback = $routeDefinitionCallback;
        $this->routeParser = $routeParser;
        $this->dataGenerator = $dataGenerator;
        $this->dispatcher = $dispatcher;
        $this->routesConfiguration = $routesConfiguration;
        $this->uriGenerator = $uriGenerator;
    }

    /**
     * @param Closure $routeDefinitionCallback
     * @return FastRoute
     */
    public static function recommendedSettings(Closure $routeDefinitionCallback): self
    {
        return new self(
            $routeDefinitionCallback,
            RouteParser\Std::class,
            DataGenerator\MarkBased::class,
            Dispatcher\MarkBased::class,
            RouteCollector::class,
            \Arris\AppRouter\FastRoute\GenerateUri\FromProcessedConfiguration::class,
        );
    }

    public function useCharCountDispatcher(): self
    {
        return $this->useCustomDispatcher(DataGenerator\CharCountBased::class, Dispatcher\CharCountBased::class);
    }

    public function useGroupCountDispatcher(): self
    {
        return $this->useCustomDispatcher(DataGenerator\GroupCountBased::class, Dispatcher\GroupCountBased::class);
    }

    public function useGroupPosDispatcher(): self
    {
        return $this->useCustomDispatcher(DataGenerator\GroupPosBased::class, Dispatcher\GroupPosBased::class);
    }

    public function useMarkDispatcher(): self
    {
        return $this->useCustomDispatcher(DataGenerator\MarkBased::class, Dispatcher\MarkBased::class);
    }

    /**
     * @param class-string<DataGenerator> $dataGenerator
     * @param class-string<Dispatcher>    $dispatcher
     */
    public function useCustomDispatcher(string $dataGenerator, string $dispatcher): self
    {
        return new self(
            $this->routeDefinitionCallback,
            $this->routeParser,
            $dataGenerator,
            $dispatcher,
            $this->routesConfiguration,
            $this->uriGenerator
        );
    }

    /** @param class-string<GenerateUri> $uriGenerator */
    public function withUriGenerator(string $uriGenerator): self
    {
        return new self(
            $this->routeDefinitionCallback,
            $this->routeParser,
            $this->dataGenerator,
            $this->dispatcher,
            $this->routesConfiguration,
            $uriGenerator
        );
    }

    /** @return ProcessedData */
    private function buildConfiguration(): array
    {
        if ($this->processedConfiguration !== null) {
            return $this->processedConfiguration;
        }

        $loader = function (): array {
            $configuredRoutes = new $this->routesConfiguration(
                new $this->routeParser(),
                new $this->dataGenerator(),
            );

            ($this->routeDefinitionCallback)($configuredRoutes);

            return $configuredRoutes->processedRoutes();
        };

        return $this->processedConfiguration = $loader();
    }

    public function dispatcher(): Dispatcher
    {
        return new $this->dispatcher($this->buildConfiguration());
    }

    public function uriGenerator(): GenerateUri
    {
        return new $this->uriGenerator($this->buildConfiguration()[2]);
    }
}
