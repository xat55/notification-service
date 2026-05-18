<?php

return [
    "default" => "default",
    
    "documentations" => [
        "default" => [
            "api" => [
                "title" => "Notification Service API",
                "description" => "API для массовой рассылки уведомлений",
            ],
            "routes" => [
                "api" => "api/documentation",
            ],
            "paths" => [
                "docs" => storage_path("api-docs"),
                "annotations" => base_path("app"),
                "docs_json" => "api-docs.json",
                "docs_yaml" => "api-docs.yaml",
                "format_to_use_for_docs" => "json",
                "swagger_ui" => base_path("vendor/swagger-api/swagger-ui"),
            ],
        ],
    ],
    
    "generate_yaml_copy" => false,
    
    "generate_always" => true,
    
    "proxy" => false,
    
    "swagger_version" => "3.0",
    
    "constants" => [
        "L5_SWAGGER_CONST_HOST" => env("APP_URL", "http://localhost:8080"),
    ],
];
