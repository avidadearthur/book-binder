<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    // Add more directories to exclude if needed
;

return (new PhpCsFixer\Config())
    ->setRules([
        // Enable Symfony rules
        '@Symfony' => true,

        // Custom rules specific to your project
        'php_version' => '8.0', // Ensure PHP 8 compatibility

        // Naming conventions
        'function_declaration' => ['style' => 'camel_case'], // Use camelCase for function names
        'function_call' => ['style' => 'camel_case'], // Use camelCase for function calls
        'method_argument_space' => ['ensure_fully_multiline' => true], // Multiline arguments in methods

        // Code style preferences
        'blank_line_before_return' => true, // Add a blank line before return statements
        'class_attributes_separation' => ['elements' => ['method']], // Separate methods with a blank line
        'multiline_comment_opening_closing' => true, // Multiline comments should have opening and closing lines
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
            ],
        ], // Remove extra blank lines

        // Symfony-specific preferences
        'concat_space' => ['spacing' => 'one'], // Use single space for concatenation
        'array_syntax' => ['syntax' => 'short'], // Use short array syntax
        'unary_operator_spaces' => true, // Add spaces around unary operators
        'binary_operator_spaces' => true, // Add spaces around binary operators
        'method_chaining_indentation' => true, // Indentation for method chaining
        'native_function_invocation' => ['include' => ['@all']], // Allow native function invocations

        // Formatting preferences
        'single_quote' => true, // Use single quotes for strings when possible
        'array_indentation' => true, // Indentation for arrays
        'no_trailing_whitespace' => true, // Remove trailing whitespace
        'trailing_comma_in_multiline' => true, // Add trailing comma in multiline arrays
        'trim_array_spaces' => true, // Remove spaces around array indexes

        // Other rules you may want to add or customize

    ])
    ->setFinder($finder)
;

