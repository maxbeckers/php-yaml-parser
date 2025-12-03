<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\YamlParserException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YamlTestSuiteTest extends TestCase
{
    private static array $SKIPPED_TESTS = [
        // parsing errors
        '26DV', '2AUY', '2EBW', '2JQS', '2LFX', '2XXW', '3RLN-01', '3RLN-04', '4H7K', '4HVU', '4JVG', '55WF', '57H4', '58MP', '5LLU',
        '5MUD', '5T43', '5U3A', '6BFJ', '6JTT', '6JWB', '6LVF', '6PBE', '7BMT', '7W2P', '8KB6', '93JH', '9BXH', '9KAX', '9MAG', '9SA2',
        'A2M4', 'AB8U', 'BEC7', 'BU8L', 'CML9', 'CTN5', 'DC7X', 'DK4H', 'F2C7', 'F8F9', 'FBC9', 'FH7J', 'GDY7', 'H7J7', 'H7TQ', 'HM87-01',
        'HRE5', 'J3BT', 'JKF3', 'JTV5', 'K3WX', 'KH5V-01', 'KK5P', 'M2N8-00', 'M5DY', 'MUS6-00', 'MUS6-04', 'MUS6-05', 'MUS6-06', 'N782',
        'NHX8', 'NJ66', 'NKF9', 'PW8X', 'Q4CL', 'QLJ7', 'RZP5', 'S7BG', 'SF5V', 'SM9W-01', 'T833', 'U3XV', 'UKK6-00', 'UKK6-01', 'UKK6-02',
        'UT92', 'VJP3-01', 'W9L4', 'XW4D', 'Y79Y-006', 'Y79Y-008', 'Y79Y-009', 'ZF4X', 'ZVH3', 'ZXT5',
        // exceptions expected
        '236B', '2CMS', '2G84-00', '2G84-01', '3HFZ', '4EJS', '5TRB', '62EZ', '6S55', '7LBH', '7MNF', '8XDJ', '9C9N', '9CWY', '9HCY',
        '9JBA', '9KBC', '9MMA', '9MQT-01', 'B63P', 'BD7L', 'BF9H', 'BS4K', 'C2SP', 'CQ3W', 'CVW2', 'CXX2', 'D49Q', 'DK95-01', 'DK95-06',
        'DMG6', 'EB22', 'EW3V', 'G5U8', 'G7JE', 'G9HC', 'GT5M', 'HU3P', 'JY7Z', 'KS4U', 'LHL4', 'MUS6-01', 'N4JP', 'P2EQ', 'QB6E', 'RHX7',
        'RXY3', 'S4GJ', 'S98Z', 'SR86', 'SU5Z', 'SU74', 'SY6V', 'TD5N', 'U44R', 'U99R', 'VJP3-00', 'X4QW', 'Y79Y-000', 'Y79Y-003',
        'Y79Y-004', 'Y79Y-005', 'Y79Y-007', 'YJV2', 'ZCZ6', 'ZL4Z',
    ];

    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    #[DataProvider('yamlTestSuiteProvider')]
    public function testYamlTestSuite(string $shortcode, string $dir)
    {
        if (in_array($shortcode, self::$SKIPPED_TESTS, true)) {
            $this->markTestSkipped('Test ' . $shortcode . ' is skipped, as it is not yet supported.');
        }

        $file = $dir . \DIRECTORY_SEPARATOR . 'in.yaml';

        $isErrorExpected = false;
        if (file_exists($dir . \DIRECTORY_SEPARATOR . 'error')) {
            $isErrorExpected = true;
            $this->expectException(YamlParserException::class);
        }

        // Uncomment to see which file is being parsed
        // echo 'Parsing file: ' . $file . PHP_EOL;
        $yaml = $this->yamlParser->parseFile($file);
        if (!$isErrorExpected) {
            $this->assertInstanceOf(\ArrayObject::class, $yaml);
        }
    }

    public static function yamlTestSuiteProvider(): array
    {
        if (!file_exists(__DIR__ . \DIRECTORY_SEPARATOR . 'yaml-test-suite' . \DIRECTORY_SEPARATOR . 'data')) {
            self::markTestSkipped('YAML Test Suite data folder not found. Please run "make data-update" to download the test suite.');
        }

        $dataDir = __DIR__ . \DIRECTORY_SEPARATOR . 'yaml-test-suite' . \DIRECTORY_SEPARATOR . 'data';
        $shortcodeDirs = glob($dataDir . \DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        $testDirs = [];
        foreach ($shortcodeDirs as $shortcodeDir) {
            $shortcode = basename($shortcodeDir);

            if (file_exists($shortcodeDir . \DIRECTORY_SEPARATOR . 'in.yaml')) {
                $testDirs[] = [$shortcode, $shortcodeDir];
            } else {
                $subDirs = glob($shortcodeDir . \DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                foreach ($subDirs as $subDir) {
                    if (file_exists($subDir . \DIRECTORY_SEPARATOR . 'in.yaml')) {
                        $shortcodeSub = $shortcode . '-' . basename($subDir);
                        $testDirs[] = [$shortcodeSub, $subDir];
                    }
                }
            }
        }

        return $testDirs;
    }
}
