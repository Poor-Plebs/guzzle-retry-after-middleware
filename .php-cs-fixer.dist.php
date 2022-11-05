<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setUsingCache(true)
    ->setCacheFile('./cache/.php_cs.cache')
    ->setRiskyAllowed(true)
    ->setFinder((new Finder())
        ->in(__DIR__)
        ->exclude([
            'cache',
            'coverage',
            'vendor',
        ])
        ->name('*.php')
        ->ignoreDotFiles(true)
        ->ignoreVCS(true)
        ->ignoreVCSIgnored(true)
    )
    ->setRules([
        '@PSR12' => true,
        'psr_autoloading' => true,
        'yoda_style' => [
            'always_move_variable' => false,
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
        'array_syntax' => ['syntax' => 'short'],
        'linebreak_after_opening_tag' => true,
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => false,
        'object_operator_without_whitespace' => true,
        'phpdoc_order' => true,
        'blank_line_before_statement' => [
            'statements' => [
                'declare',
                'return',
            ],
        ],
        'declare_strict_types' => true,
        'php_unit_strict' => true,
        'single_quote' => true,
        'blank_line_after_opening_tag' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'continue',
                'curly_brace_block',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'throw',
                'use',
                'use_trait',
                'switch',
                'case',
                'default',
            ],
        ],
        'no_whitespace_in_blank_line' => true,
        'no_blank_lines_after_class_opening' => true,
        'return_type_declaration' => [
            'space_before' => 'none',
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'compact_nullable_typehint' => true,
        'ternary_operator_spaces' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'no_leading_import_slash' => true,
        'is_null' => true,
        'modernize_types_casting' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_singleline_whitespace_before_semicolons' => true,
        'multiline_whitespace_before_semicolons' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public_static',
                'property_protected_static',
                'property_private_static',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
                'method_public_abstract',
                'method_protected_abstract',
                'method_public_abstract_static',
                'method_protected_abstract_static',
                'method_public_static',
                'method_protected_static',
                'method_private_static',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'cast_spaces' => [
            'space' => 'none',
        ],
        'trailing_comma_in_multiline' => true,
        'trim_array_spaces' => true,
        'short_scalar_cast' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'function_typehint_space' => true,
        'function_declaration' => [
            'closure_function_spacing' => 'one',
        ],
        'list_syntax' => [
            'syntax' => 'short',
        ],
        'array_indentation' => true,
        'method_chaining_indentation' => true,
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => false,
            'allow_unused_params' => false,
            'remove_inheritdoc' => true,
        ],
        'no_empty_phpdoc' => true,
        'phpdoc_trim' => true,
        'phpdoc_indent' => true,
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'normalize_index_brace' => true,
        'php_unit_method_casing' => [
            'case' => 'snake_case',
        ],
        'no_empty_comment' => true,
        'fully_qualified_strict_types' => true,
        'php_unit_expectation' => true,
        'php_unit_construct' => true,
        'php_unit_dedicate_assert' => true,
        'php_unit_dedicate_assert_internal_type' => true,
        'php_unit_mock' => true,
        'php_unit_mock_short_will_return' => true,
        'php_unit_namespaced' => true,
        'php_unit_set_up_tear_down_visibility' => true,
    ]);
