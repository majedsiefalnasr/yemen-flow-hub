<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\Catalog\SeederCatalog;
use PHPUnit\Framework\TestCase;

class SeederCatalogIntegrityTest extends TestCase
{
    /**
     * Test all SeederCatalog hook constants are unique and valid references.
     */
    public function test_anchor_constants_unique_and_valid(): void
    {
        $reflClass = new \ReflectionClass(SeederCatalog::class);
        $constants = $reflClass->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        
        // Extract anchor hook constants (those that start with ANCHOR_ and are strings)
        $anchorRefs = array_filter(
            $constants,
            fn ($value, $name) => str_starts_with($name, 'ANCHOR_') && is_string($value),
            ARRAY_FILTER_USE_BOTH
        );
        
        $values = array_values($anchorRefs);
        
        // Check all are unique
        $this->assertCount(
            count(array_unique($values)),
            $values,
            'Anchor hook constants must be unique'
        );
        
        // Check all match regex pattern
        foreach ($values as $ref) {
            $this->assertTrue(
                SeederCatalog::isValidReference($ref),
                "Anchor ref '$ref' does not match pattern ^ENG-2026-(YBRD|CAC)-[AB]\d{3}$"
            );
        }
    }

    /**
     * Test engine request scenario matrix sums to exactly 250.
     */
    public function test_bulk_matrix_sums_to_250(): void
    {
        $baseDir = dirname(__DIR__, 3);
        $scenarios = require $baseDir . '/database/seeders/catalog/engine-request-scenarios.php';
        
        $this->assertIsArray($scenarios, 'Scenarios file must return array');
        
        $total = array_reduce(
            $scenarios,
            fn ($sum, $row) => $sum + $row[1],
            0
        );
        
        $this->assertEquals(
            250,
            $total,
            sprintf('Bulk matrix must sum to 250, got %d', $total)
        );
    }

    /**
     * Test anchor catalog has exactly 56 specs.
     */
    public function test_anchor_catalog_count_is_56(): void
    {
        $baseDir = dirname(__DIR__, 3);
        $anchors = require $baseDir . '/database/seeders/catalog/anchor-catalog.php';
        
        $this->assertIsArray($anchors, 'Anchor catalog must return array');
        $this->assertCount(56, $anchors, 'Anchor catalog must have exactly 56 specs');
    }

    /**
     * Test bulk scenario keys are valid (non-empty strings).
     */
    public function test_bulk_scenario_keys_valid(): void
    {
        $baseDir = dirname(__DIR__, 3);
        $scenarios = require $baseDir . '/database/seeders/catalog/engine-request-scenarios.php';
        
        // Extract unique scenario keys from bulk
        $bulkScenarioKeys = array_unique(
            array_map(fn ($row) => $row[0], $scenarios)
        );
        
        // All bulk keys should be non-empty strings
        foreach ($bulkScenarioKeys as $key) {
            $this->assertIsString($key);
            $this->assertNotEmpty($key);
        }
    }

    /**
     * Test all anchor references are unique and match pattern.
     */
    public function test_anchor_references_unique_and_valid(): void
    {
        $baseDir = dirname(__DIR__, 3);
        $anchors = require $baseDir . '/database/seeders/catalog/anchor-catalog.php';
        
        $refs = array_map(fn ($spec) => $spec['reference'], $anchors);
        
        // All unique
        $this->assertCount(
            count(array_unique($refs)),
            $refs,
            'Anchor references must be unique'
        );
        
        // All match pattern
        foreach ($refs as $ref) {
            $this->assertTrue(
                SeederCatalog::isValidReference($ref),
                "Anchor reference '$ref' does not match valid pattern"
            );
        }
    }

    /**
     * Test 28 anchors per bank (56 total).
     */
    public function test_anchor_counts_per_bank(): void
    {
        $baseDir = dirname(__DIR__, 3);
        $anchors = require $baseDir . '/database/seeders/catalog/anchor-catalog.php';
        
        $ybrdCount = count(array_filter($anchors, fn ($s) => $s['bank'] === 'YBRD'));
        $cacCount = count(array_filter($anchors, fn ($s) => $s['bank'] === 'CAC'));
        
        $this->assertEquals(28, $ybrdCount, 'Must have 28 YBRD anchors');
        $this->assertEquals(28, $cacCount, 'Must have 28 CAC anchors');
    }

    /**
     * Test anchor bank code matches bank in reference.
     */
    public function test_anchor_bank_matches_reference(): void
    {
        $baseDir = dirname(__DIR__, 3);
        $anchors = require $baseDir . '/database/seeders/catalog/anchor-catalog.php';
        
        foreach ($anchors as $spec) {
            $ref = $spec['reference'];
            $bank = $spec['bank'];
            
            $this->assertStringContainsString(
                "-$bank-",
                $ref,
                "Anchor reference '$ref' must contain bank code '$bank'"
            );
        }
    }

    /**
     * Test DEMO_YEAR constant is 2026 (immutable).
     */
    public function test_demo_year_is_2026(): void
    {
        $this->assertEquals(2026, SeederCatalog::DEMO_YEAR);
    }

    /**
     * Test exact counts defined.
     */
    public function test_exact_counts(): void
    {
        $this->assertEquals(56, SeederCatalog::ANCHOR_COUNT);
        $this->assertEquals(250, SeederCatalog::BULK_COUNT);
        $this->assertEquals(306, SeederCatalog::TOTAL_COUNT);
    }

    /**
     * Test reference builder helpers work correctly.
     */
    public function test_reference_builders(): void
    {
        $this->assertEquals('ENG-2026-YBRD-A001', SeederCatalog::anchorRef('YBRD', 1));
        $this->assertEquals('ENG-2026-CAC-A028', SeederCatalog::anchorRef('CAC', 28));
        $this->assertEquals('ENG-2026-YBRD-B001', SeederCatalog::bulkRef('YBRD', 1));
        $this->assertEquals('ENG-2026-CAC-B125', SeederCatalog::bulkRef('CAC', 125));
    }

    /**
     * Test reference validation regex.
     */
    public function test_reference_validation(): void
    {
        // Valid
        $this->assertTrue(SeederCatalog::isValidReference('ENG-2026-YBRD-A001'));
        $this->assertTrue(SeederCatalog::isValidReference('ENG-2026-CAC-B125'));
        
        // Invalid
        $this->assertFalse(SeederCatalog::isValidReference('ENG-2026-YBRD-C001')); // wrong kind
        $this->assertFalse(SeederCatalog::isValidReference('ENG-2025-YBRD-A001')); // wrong year
        $this->assertFalse(SeederCatalog::isValidReference('ENG-2026-YBR-A001')); // wrong bank
    }
}
