<?php
/**
 * PHP Action runner
 *
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

 // Context is a wrapper type for the optional second parameter.
class Context {
    public $functionName;
    public $functionVersion;
    public $activationId;
    public $requestId;
    public $deadline;
    public $apiHost;
    public $apiKey;
    public $namespace;

    function getRemainingTimeInMillis() {
        $epochNowInMs = floor(microtime(true) * 1000);
        $deltaMs = $this->deadline - $epochNowInMs;
        return $deltaMs > 0 ? $deltaMs : 0;
    }
}

// open fd/3 as that's where we send the result
$fd3 = fopen('php://fd/3', 'wb');

// Register a shutdown function so that we can fail gracefully when a fatal error occurs
register_shutdown_function(static function () use ($fd3) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        file_put_contents('php://stderr', "An error occurred running the function.\n");
        fwrite($fd3, '{"error": "An error occurred running the function."}' . "\n");
    }
    fclose($fd3);
});

require 'vendor/autoload.php';
require 'index.php';

// retrieve main function
$__functionName = $argv[1] ?? 'main';

$sentinel = "XXX_THE_END_OF_A_WHISK_ACTIVATION_XXX\n";

// read stdin
while ($f = fgets(STDIN)) {
    // call the function
    $data = json_decode($f ?? '', true);
    if (!is_array($data)) {
        $data = [];
    }

    // convert all parameters other than value to environment variables
    foreach ($data as $key => $value) {
        if ($key !== 'value') {
            $envKeyName = '__OW_' . strtoupper($key);
            $_ENV[$envKeyName] = $value;
            putenv($envKeyName . '=' . $value);
        }
    }

    // Construct a context.
    $context = new Context;
    $context->functionName = getenv('__OW_ACTION_NAME');
    $context->functionVersion = getenv('__OW_ACTION_VERSION');
    $context->activationId = getenv('__OW_ACTIVATION_ID');
    $context->requestId = getenv('__OW_TRANSACTION_ID');
    $context->deadline = intval(getenv('__OW_DEADLINE'));
    $context->apiHost = getenv('__OW_API_HOST');
    $context->apiKey = getenv('__OW_API_KEY') ?: "";
    $context->namespace = getenv('__OW_NAMESPACE');

    $values = $data['value'] ?? [];
    try {
        // It's safe to always pass both values in PHP as the context parameter would just be
        // ignored if the user's not accessing it.
        $result = $__functionName($values, $context);

        // convert result to an array if we can
        if (is_object($result)) {
            if (method_exists($result, 'getArrayCopy')) {
                $result = $result->getArrayCopy();
            } elseif ($result instanceof stdClass) {
                $result = (array)$result;
            }
        } elseif ($result === null) {
            $result = [];
        }

        // process the result
        if (!is_array($result)) {
            file_put_contents('php://stderr', 'Result must be an array but has type "' . gettype($result) . '": ' . $result);
            file_put_contents('php://stdout', 'The function did not return a dictionary.');
            $result = (string)$result;
        } else {
            // cast result to an object for json_encode to ensure that an empty array becomes "{}" & send to fd/3
            $result = json_encode((object)$result);
        }
    } catch (Throwable $e) {
        file_put_contents('php://stderr', (string)$e);
        $result = '{"error": "An error occurred running the function."}';
    }

    // ensure that the sentinels will be on their own lines
    file_put_contents('php://stderr', "\n" . $sentinel);
    file_put_contents('php://stdout', "\n" . $sentinel);

    fwrite($fd3, $result . "\n");
}
