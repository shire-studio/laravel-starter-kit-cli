<?php

declare(strict_types=1);

use Laravel\Boost\Mcp\Tools\ApplicationInfo;
use Laravel\Boost\Mcp\Tools\BrowserLogs;
use Laravel\Boost\Mcp\Tools\DatabaseConnections;
use Laravel\Boost\Mcp\Tools\DatabaseQuery;
use Laravel\Boost\Mcp\Tools\DatabaseSchema;
use Laravel\Boost\Mcp\Tools\GetAbsoluteUrl;
use Laravel\Boost\Mcp\Tools\LastError;
use Laravel\Boost\Mcp\Tools\ReadLogEntries;
use Laravel\Boost\Mcp\Tools\Tinker;

return [

    /*
    |--------------------------------------------------------------------------
    | Boost Browser Logs Watcher
    |--------------------------------------------------------------------------
    |
    | The browser logs watcher registers an HTTP route and middleware, which
    | a console application does not have. Boost's remaining options use
    | the package defaults, merged in by the Boost service provider.
    |
    */

    'browser_logs_watcher' => false,

    /*
    |--------------------------------------------------------------------------
    | Excluded Guidelines
    |--------------------------------------------------------------------------
    |
    | The deployment guideline describes deploying a web application, which
    | does not apply to a console application distributed as a binary.
    |
    */

    'guidelines' => [
        'exclude' => ['deployments'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded MCP Tools
    |--------------------------------------------------------------------------
    |
    | These tools depend on a database, logging, HTTP, or Tinker, none of
    | which the console application installs. Loading them would break
    | the MCP server, so only the documentation and rule tools remain.
    |
    */

    'mcp' => [
        'tools' => [
            'exclude' => [
                ApplicationInfo::class,
                BrowserLogs::class,
                DatabaseConnections::class,
                DatabaseQuery::class,
                DatabaseSchema::class,
                GetAbsoluteUrl::class,
                LastError::class,
                ReadLogEntries::class,
                Tinker::class,
            ],
        ],
    ],

];
