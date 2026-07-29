<?php

namespace MaxBeckers\YamlParser\Tests;

use MaxBeckers\YamlParser\Exception\YamlParserException;
use MaxBeckers\YamlParser\YamlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YamlTestSuiteTest extends TestCase
{
    private const SKIPPED_TESTS = [
        '236B', '26DV', '2AUY', '2CMS', '2EBW', '2XXW', '3HFZ', '57H4',
        '58MP', '5MUD', '5T43', '5TRB', '62EZ', '6BFJ', '6JWB', '6PBE', '6S55', '7BMT', '7LBH', '7MNF', '7W2P', '8KB6', '8XDJ',
        '93JH', '9C9N', '9CWY', '9HCY', '9KAX', '9KBC', '9MMA', '9MQT-01', 'A2M4', 'AB8U', 'B63P', 'BD7L',
        'BF9H', 'BS4K', 'BU8L', 'C2SP', 'CQ3W', 'CXX2', 'D49Q', 'DK95-01', 'DMG6', 'EB22', 'EW3V', 'F2C7',
        'FBC9', 'G5U8', 'G7JE', 'G9HC', 'GT5M', 'HM87-01', 'HU3P', 'JTV5', 'JY7Z', 'K3WX', 'KK5P',
        'KS4U', 'LHL4', 'M2N8-00', 'M5DY', 'MUS6-01', 'N4JP', 'NJ66', 'P2EQ', 'PW8X',
        'QB6E', 'RHX7', 'RXY3', 'RZP5', 'SR86', 'SU74', 'SY6V', 'TD5N', 'U3XV', 'U44R', 'U99R',
        'UKK6-02', 'UT92', 'VJP3-00', 'VJP3-01', 'XW4D', 'Y79Y-003', 'Y79Y-004', 'Y79Y-005',
        'Y79Y-007', 'YJV2', 'ZCZ6', 'ZL4Z',
    ];

    private YamlParser $yamlParser;

    protected function setUp(): void
    {
        $this->yamlParser = new YamlParser();
    }

    #[DataProvider('yamlTestSuiteProvider')]
    public function testYamlTestSuite(string $testName, string $shortcode, string $file, bool $isErrorExpected)
    {
        if ('' === $file) {
            $this->markTestSkipped('The YAML test suite is not available: ' . $testName);
        }

        if (in_array($shortcode, self::SKIPPED_TESTS, true)) {
            if ($isErrorExpected) {
                $this->markTestSkipped('Test "' . $testName . '" is skipped, as error was expected but not yet supported.');
            } else {
                $this->markTestSkipped('Test "' . $testName . '" is skipped, as it is not yet supported.');
            }
        }

        if ($isErrorExpected) {
            $this->expectException(YamlParserException::class);
        }

        $data = $this->yamlParser->parseFile($file);

        if (!$isErrorExpected) {
            if (is_scalar($data)) {
                $this->assertIsScalar($data);

                return;
            }

            $this->assertInstanceOf(\ArrayObject::class, $data);
        }
    }

    public static function yamlTestSuiteProvider(): \Generator
    {
        $dataDir = dirname(__DIR__) . '/vendor/yaml/yaml-test-suite';
        $shortcodeDirs = glob($dataDir . '/*', GLOB_ONLYDIR);

        if (empty($shortcodeDirs)) {
            yield 'yaml-test-suite-empty' => ['YAML Test Suite Empty', '', '', false, null];

            return;
        }

        foreach ($shortcodeDirs as $shortcodeDir) {
            $shortcode = basename($shortcodeDir);
            if (in_array($shortcode, ['name', 'tags'], true)) {
                continue;
            }

            if (file_exists($shortcodeDir . '/in.yaml')) {
                $isErrorExpected = file_exists($shortcodeDir . '/error');
                $testName = is_file($shortcodeDir . '/===') ? trim(file_get_contents($shortcodeDir . '/===')) : $shortcode;
                yield $shortcode => [$testName, $shortcode, $shortcodeDir . '/in.yaml', $isErrorExpected];
            } else {
                $subDirs = glob($shortcodeDir . '/*', GLOB_ONLYDIR);
                foreach ($subDirs as $subDir) {
                    if (file_exists($subDir . '/in.yaml')) {
                        $shortcodeSub = $shortcode . '-' . basename($subDir);
                        $testName = is_file($subDir . '/===') ? trim(file_get_contents($subDir . '/===')) : $shortcode;
                        $isErrorExpected = file_exists($subDir . '/error');
                        yield $shortcodeSub => [$testName, $shortcodeSub, $subDir . '/in.yaml', $isErrorExpected];
                    }
                }
            }
        }
    }
}
