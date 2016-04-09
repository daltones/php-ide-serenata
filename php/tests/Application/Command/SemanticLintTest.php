<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class SemanticLintTest extends IndexedTest
{
    protected function lintFile($file)
    {
        $path = __DIR__ . '/SemanticLintTest/' . $file;

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new SemanticLint();
        $command->setIndexDatabase($indexDatabase);

        return $command->semanticLint($path, file_get_contents($path));
    }

    public function testReportsUnknownClassesWithNoNamespace()
    {
        $output = $this->lintFile('UnknownClassesNoNamespace.php');

        $this->assertEquals($output['errors']['unknownClasses'], [
            [
                'name'      => 'A\B',
                'namespace' => null,
                'start'     => 16,
                'end'       => 19
            ]
        ]);
    }

    public function testReportsUnknownClassesWithSingleNamespace()
    {
        $output = $this->lintFile('UnknownClassesSingleNamespace.php');

        $this->assertEquals($output['errors']['unknownClasses'], [
            [
                'name'      => 'DateTime',
                'namespace' => 'A',
                'start'     => 64,
                'end'       => 72
            ],
            [
                'name'      => 'DateTimeZone',
                'namespace' => 'A',
                'start'     => 85,
                'end'       => 97
            ]
        ]);
    }

    public function testReportsUnknownClassesWithMultipleNamespaces()
    {
        $output = $this->lintFile('UnknownClassesMultipleNamespaces.php');

        $this->assertEquals($output['errors']['unknownClasses'], [
            [
                'name'      => 'DateTime',
                'namespace' => 'A',
                'start'     => 56,
                'end'       => 64
            ],

            [
                'name'      => 'SplFileInfo',
                'namespace' => 'B',
                'start'     => 117,
                'end'       => 128
            ]
        ]);
    }

    public function testReportsUnknownClassesInDocBlocks()
    {
        $output = $this->lintFile('UnknownClassesDocblock.php');

        $this->assertEquals($output['errors']['unknownClasses'], [
            [
                'name'      => 'A\B',
                'namespace' => 'A',
                'start'     => 120,
                'end'       => 121
            ],

            [
                'name'      => 'A\C',
                'namespace' => 'A',
                'start'     => 120,
                'end'       => 121
            ]
        ]);
    }

    public function testReportsUnusedUseStatementsWithSingleNamespace()
    {
        $output = $this->lintFile('UnusedUseStatementsSingleNamespace.php');

        $this->assertEquals($output['warnings']['unusedUseStatements'], [
            [
                'name'  => 'Traversable',
                'alias' => 'Traversable',
                'start' => 39,
                'end'   => 50
            ]
        ]);
    }

    public function testReportsUnusedUseStatementsWithMultipleNamespaces()
    {
        $output = $this->lintFile('UnusedUseStatementsMultipleNamespaces.php');

        $this->assertEquals($output['warnings']['unusedUseStatements'], [
            [
                'name'  => 'SplFileInfo',
                'alias' => 'SplFileInfo',
                'start' => 47,
                'end'   => 58
            ],

            [
                'name'  => 'DateTime',
                'alias' => 'DateTime',
                'start' => 111,
                'end'   => 119
            ]
        ]);
    }

    public function testSeesUseStatementsAsUsedIfTheyAppearInComments()
    {
        $output = $this->lintFile('UnusedUseStatementsDocblock.php');

        $this->assertEquals($output['warnings']['unusedUseStatements'], [
            [
                'name'  => 'SplMinHeap',
                'alias' => 'SplMinHeap',
                'start' => 39,
                'end'   => 49
            ],

            [
                'name'  => 'SplFileInfo',
                'alias' => 'SplFileInfo',
                'start' => 72,
                'end'   => 83
            ]
        ]);
    }

    public function testCorrectlyIdentifiesMissingDocumentation()
    {
        $output = $this->lintFile('DocblockCorrectnessMissingDocumentation.php');

        $this->assertEquals([
            [
                'name'  => 'some_function',
                'line'  => 5,
                'start' => 21,
                'end'   => 49
            ],

            // [
            //     'name'  => 'SOME_CONST',
            //     'class' => 'C',
            //     'start' => 72,
            //     'end'   => 83
            // ],
            //
            // [
            //     'name'  => 'someProperty',
            //     'class' => 'C',
            //     'start' => 72,
            //     'end'   => 83
            // ],
            //
            // [
            //     'name'  => 'someBaseClassMethod',
            //     'class' => 'C',
            //     'start' => 72,
            //     'end'   => 83
            // ],
            //
            // [
            //     'name'  => 'someMethod',
            //     'class' => 'C',
            //     'start' => 72,
            //     'end'   => 83
            // ]
        ], $output['warnings']['docblockIssues']['missingDocumentation']);
    }

    public function testCorrectlyIdentifiesDocblockMissingParameter()
    {
        $output = $this->lintFile('DocblockCorrectnessMissingParameter.php');

        $this->assertEquals([
            [
                'name'      => 'some_function_missing_parameter',
                'line'      => 17,
                'start'     => 186,
                'end'       => 258,
                'parameter' => 'param2'
            ]
        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testCorrectlyIdentifiesDocblockParameterTypeMismatch()
    {
        $output = $this->lintFile('DocblockCorrectnessParameterTypeMismatch.php');

        $this->assertEquals([
            [
                'name'  => 'some_function_parameter_incorrect_type',
                'line'  => 5,
                'start' => 21,
                'end'   => 49
            ],
        ], $output['warnings']['docblockIssues']['parameterTypeMismatch']);
    }

    public function testCorrectlyIdentifiesDocblockSuperfluousParameters()
    {
        $output = $this->lintFile('DocblockCorrectnessSuperfluousParameters.php');

        $this->assertEquals([
            [
                'name'  => 'some_function_extra_parameter',
                'line'  => 5,
                'start' => 21,
                'end'   => 49
            ]
        ], $output['warnings']['docblockIssues']['superfluousParameters']);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new SemanticLint();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $output = $this->lintFile('MissingFile.php');
    }
}
