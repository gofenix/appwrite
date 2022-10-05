<?php

namespace Appwrite\GraphQL;

use Redis;
use Utopia\App;
use Utopia\Database\Database;

class ResolverRegistry
{
    private static array $resolverMapping = [];

    public static function has(string $type): bool
    {
        return isset(static::$resolverMapping[$type]);
    }

    public static function get(
        string $type,
        string $field,
        App $utopia,
        Redis $cache,
        ?string $projectId = null,
        ?Database $dbForProject = null,
        ?string $path = null,
        ?string $method = null,
        ?string $databaseId = null,
        ?string $collectionId = null,
    ): ?callable {
        if (static::has($field)) {
            return static::$resolverMapping[$field];
        }
        return match ($type) {
            'api' => static::createAPIResolver(
                $field,
                $utopia,
                $cache,
                $path,
                $method,
            ),
            'collection' => static::createCollectionResolver(
                $field,
                $utopia,
                $cache,
                $projectId,
                $dbForProject,
                $method,
                $databaseId,
                $collectionId,
            ),
            default => static::$resolverMapping[$field],
        };
    }

    public static function set(string $field, callable $resolver): void
    {
        static::$resolverMapping[$field] = $resolver;
    }

    public static function clear(): void
    {
        static::$resolverMapping = [];
    }

    private static function createAPIResolver(
        string $field,
        App $utopia,
        Redis $cache,
        ?string $path = null,
        ?string $method = null
    ): callable {
        if ($path && $method) {
            $cache->set('graphql:api:meta:' . $field, \json_encode([
                'path' => $path,
                'method' => $method,
            ]));
        } else {
            $route = \json_decode($cache->get('graphql:api:meta:' . $field), true);
            $path = $route['path'];
            $method = $route['method'];
        }
        return static::$resolverMapping[$field] = Resolvers::resolveAPIRequest(
            $utopia,
            $path,
            $method,
        );
    }

    private static function createCollectionResolver(
        string $field,
        App $utopia,
        Redis $cache,
        string $projectId,
        ?Database $dbForProject = null,
        ?string $method = null,
        ?string $databaseId = null,
        ?string $collectionId = null,
    ): callable {
        if ($databaseId && $collectionId && $method) {
            $cache->set('graphql:collections:' . $projectId . ':meta:' . $field, \json_encode([
                'databaseId' => $databaseId,
                'collectionId' => $collectionId,
                'method' => $method,
            ]));
        } else {
            $ids = \json_decode($cache->get('graphql:collections:' . $projectId . ':meta:' . $field));
            $databaseId = $ids['databaseId'];
            $collectionId = $ids['collectionId'];
            $method = $ids['method'];
        }

        return static::$resolverMapping[$field] = Resolvers::resolveDocument(
            $utopia,
            $dbForProject,
            $databaseId,
            $collectionId,
            $method,
        );
    }
}