<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YamlTestSuiteTest extends TestCase
{
    private static array $SKIPPED_TESTS = [
        // Currently not correctly implemented features are skipped
        '26DV', '2AUY', '2EBW', '2JQS', '2LFX', '2XXW', '4H7K', '4HVU', '4JVG', '55WF', '57H4', '58MP', '5LLU', '5MUD', '5T43', '5U3A', '6BFJ', '6JTT',
        '6JWB', '6LVF', '6PBE', '7BMT', '7W2P', '8KB6', '93JH', '9BXH', '9KAX', '9MAG', '9SA2', 'A2M4', 'AB8U', 'BEC7', 'BU8L', 'CML9', 'CTN5', 'DC7X',
        'DK4H', 'F2C7', 'F8F9', 'FBC9', 'FH7J', 'GDY7', 'H7J7', 'H7TQ', 'HRE5', 'J3BT', 'JKF3', 'JTV5', 'K3WX', 'KK5P', 'M5DY', 'N782', 'NHX8', 'NJ66',
        'NKF9', 'PW8X', 'Q4CL', 'QLJ7', 'RZP5', 'S7BG', 'SF5V', 'T833', 'U3XV', 'UT92', 'W9L4', 'XW4D', 'ZF4X', 'ZVH3', 'ZXT5',
    ];

    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    #[DataProvider('yamlTestSuiteProvider')]
    public function testYamlTestSuite(string $file)
    {
        $shortcode = substr($file, strrpos($file, 'data' . \DIRECTORY_SEPARATOR) + 5, 4);

        if (in_array($shortcode, self::$SKIPPED_TESTS, true)) {
            $this->markTestSkipped('Test ' . $file . ' is skipped, as it is not yet supported.');
        }

        // Uncomment to see which file is being parsed
        // echo 'Parsing file: ' . $file . PHP_EOL;
        $yaml = $this->yamlParser->parseFile($file);
        $this->assertInstanceOf(\ArrayObject::class, $yaml);
    }

    public static function yamlTestSuiteProvider(): array
    {
        exec('which make 2>/dev/null', $out, $code);
        if ($code !== 0 && !file_exists(__DIR__ . \DIRECTORY_SEPARATOR . 'yaml-test-suite' . \DIRECTORY_SEPARATOR . 'data')) {
            self::markTestSkipped('Make command not found, skipping YAML Test Suite setup.');
        }

        if ($code === 0) {
            exec('make -C ' . escapeshellarg(__DIR__ . \DIRECTORY_SEPARATOR . 'yaml-test-suite') . ' data', $output, $makeCode);
            if ($makeCode !== 0) {
                self::markTestSkipped('Failed to set up YAML Test Suite using make command.');
            }
        }

        $files = glob(__DIR__ . \DIRECTORY_SEPARATOR . 'yaml-test-suite' . \DIRECTORY_SEPARATOR . 'data' . \DIRECTORY_SEPARATOR . '*' . \DIRECTORY_SEPARATOR . 'in.yaml');

        $testFiles = [];
        foreach ($files as $file) {
            $testFiles[] = [$file];
        }

        return $testFiles;
    }
}
