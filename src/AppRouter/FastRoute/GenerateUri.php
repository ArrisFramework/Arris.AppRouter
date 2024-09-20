<?php
declare(strict_types=1);

namespace Arris\AppRouter\FastRoute;

use Arris\AppRouter\FastRoute\GenerateUri\UriCouldNotBeGenerated;

/**
 * @phpstan-import-type ParsedRoutes from RouteParser
 * @phpstan-type RoutesForUriGeneration array<non-empty-string, ParsedRoutes>
 * @phpstan-type UriSubstitutions array<non-empty-string, non-empty-string>
 */
interface GenerateUri
{
    /**
     * @param UriSubstitutions $substitutions
     *
     * @throws UriCouldNotBeGenerated
     */
    public function forRoute(string $name, array $substitutions = []): string;
}
