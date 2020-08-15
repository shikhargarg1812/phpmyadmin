<?php
/**
 * tests for PhpMyAdmin\CentralColumns
 */

declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\CentralColumns;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Html\Generator;
use PhpMyAdmin\Message;
use PhpMyAdmin\Types;
use PhpMyAdmin\Url;
use PhpMyAdmin\Util;
use function array_slice;
use function ceil;

/**
 * tests for PhpMyAdmin\CentralColumns
 */
class CentralColumnsTest extends AbstractTestCase
{
    /** @var CentralColumns */
    private $centralColumns;

    /** @var array<int, array<string, string|int>> */
    private $columnData = [
        [
            'col_name' => 'id',
            'col_type' => 'integer',
            'col_length' => 0,
            'col_isNull' => 0,
            'col_extra' => 'UNSIGNED,auto_increment',
            'col_default' => 1,
            'col_collation' => '',
        ],
        [
            'col_name' => 'col1',
            'col_type' => 'varchar',
            'col_length' => 100,
            'col_isNull' => 1,
            'col_extra' => 'BINARY',
            'col_default' => 1,
            'col_collation' => '',
        ],
        [
            'col_name' => 'col2',
            'col_type' => 'DATETIME',
            'col_length' => 0,
            'col_isNull' => 1,
            'col_extra' => 'on update CURRENT_TIMESTAMP',
            'col_default' => 'CURRENT_TIMESTAMP',
            'col_collation' => '',
        ],
    ];

    /** @var array<int, array<string, string|int>> */
    private $modifiedColumnData = [
        [
            'col_name' => 'id',
            'col_type' => 'integer',
            'col_length' => 0,
            'col_isNull' => 0,
            'col_extra' => 'auto_increment',
            'col_default' => 1,
            'col_collation' => '',
            'col_attribute' => 'UNSIGNED',
        ],
        [
            'col_name' => 'col1',
            'col_type' => 'varchar',
            'col_length' => 100,
            'col_isNull' => 1,
            'col_extra' => '',
            'col_default' => 1,
            'col_collation' => '',
            'col_attribute' => 'BINARY',
        ],
        [
            'col_name' => 'col2',
            'col_type' => 'DATETIME',
            'col_length' => 0,
            'col_isNull' => 1,
            'col_extra' => '',
            'col_default' => 'CURRENT_TIMESTAMP',
            'col_collation' => '',
            'col_attribute' => 'on update CURRENT_TIMESTAMP',
        ],
    ];

    /**
     * prepares environment for tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        parent::setGlobalConfig();
        parent::defineVersionConstants();
        $GLOBALS['cfg']['Server']['user'] = 'pma_user';
        $GLOBALS['cfg']['Server']['DisableIS'] = true;
        $GLOBALS['cfg']['MaxRows'] = 10;
        $GLOBALS['cfg']['ServerDefault'] = 'PMA_server';
        $GLOBALS['cfg']['ActionLinksMode'] = 'icons';
        $GLOBALS['cfg']['CharEditing'] = '';
        $GLOBALS['cfg']['LimitChars'] = 50;
        $GLOBALS['db'] = 'PMA_db';
        $GLOBALS['table'] = 'PMA_table';

        //$_SESSION
        $GLOBALS['server'] = 1;
        $_SESSION['relation'][1] = [
            'PMA_VERSION' => PMA_VERSION,
            'centralcolumnswork' => true,
            'relwork' => 1,
            'db' => 'phpmyadmin',
            'relation' => 'relation',
            'central_columns' => 'pma_central_columns',
        ];

        // mock DBI
        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->types = new Types($dbi);
        $GLOBALS['dbi'] = $dbi;

        // set some common expectations
        $dbi->expects($this->any())
            ->method('selectDb')
            ->will($this->returnValue(true));
        $dbi->expects($this->any())
            ->method('getColumns')
            ->will(
                $this->returnValue(
                    [
                        'id' => [
                            'Type' => 'integer',
                            'Null' => 'NO',
                        ],
                        'col1' => [
                            'Type' => 'varchar(100)',
                            'Null' => 'YES',
                        ],
                        'col2' => [
                            'Type' => 'DATETIME',
                            'Null' => 'NO',
                        ],
                    ]
                )
            );
        $dbi->expects($this->any())
            ->method('getColumnNames')
            ->will($this->returnValue(['id', 'col1', 'col2']));
        $dbi->expects($this->any())
            ->method('tryQuery')
            ->will($this->returnValue(true));
        $dbi->expects($this->any())
            ->method('getTables')
            ->will(
                $this->returnValue(['PMA_table', 'PMA_table1', 'PMA_table2'])
            );
        $dbi->expects($this->any())->method('escapeString')
            ->will($this->returnArgument(0));

        $this->centralColumns = new CentralColumns($dbi);
    }

    /**
     * Test for getParams
     */
    public function testGetParams(): void
    {
        $this->assertSame(
            [
                'user' => 'pma_user',
                'db' => 'phpmyadmin',
                'table' => 'pma_central_columns',
            ],
            $this->centralColumns->getParams()
        );
    }

    /**
     * Test for getColumnsList
     */
    public function testGetColumnsList(): void
    {
        $GLOBALS['dbi']->expects($this->exactly(2))
            ->method('fetchResult')
            ->willReturnOnConsecutiveCalls(
                $this->columnData,
                array_slice($this->columnData, 1, 2)
            );

        $this->assertEquals(
            $this->modifiedColumnData,
            $this->centralColumns->getColumnsList('phpmyadmin')
        );
        $this->assertEquals(
            array_slice($this->modifiedColumnData, 1, 2),
            $this->centralColumns->getColumnsList('phpmyadmin', 1, 2)
        );
    }

    /**
     * Test for getCount
     */
    public function testGetCount(): void
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                'SELECT count(db_name) FROM `pma_central_columns` '
                . "WHERE db_name = 'phpmyadmin';",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue([3])
            );

        $this->assertEquals(
            3,
            $this->centralColumns->getCount('phpmyadmin')
        );
    }

    /**
     * Test for syncUniqueColumns
     */
    public function testSyncUniqueColumns(): void
    {
        $_POST['db'] = 'PMA_db';
        $_POST['table'] = 'PMA_table';

        $this->assertTrue(
            $this->centralColumns->syncUniqueColumns(
                ['PMA_table']
            )
        );
    }

    /**
     * Test for deleteColumnsFromList
     */
    public function testDeleteColumnsFromList(): void
    {
        $_POST['db'] = 'PMA_db';
        $_POST['table'] = 'PMA_table';

        // when column exists in the central column list
        $GLOBALS['dbi']->expects($this->at(4))
            ->method('fetchResult')
            ->with(
                'SELECT col_name FROM `pma_central_columns` '
                . "WHERE db_name = 'PMA_db' AND col_name IN ('col1');",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(['col1'])
            );

        $GLOBALS['dbi']->expects($this->at(7))
            ->method('tryQuery')
            ->with(
                'DELETE FROM `pma_central_columns` '
                . "WHERE db_name = 'PMA_db' AND col_name IN ('col1');",
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(['col1'])
            );

        $this->assertTrue(
            $this->centralColumns->deleteColumnsFromList(
                $_POST['db'],
                ['col1'],
                false
            )
        );

        // when column does not exist in the central column list
        $this->assertInstanceOf(
            Message::class,
            $this->centralColumns->deleteColumnsFromList(
                $_POST['db'],
                ['column1'],
                false
            )
        );

        $this->assertInstanceOf(
            Message::class,
            $this->centralColumns->deleteColumnsFromList(
                $_POST['db'],
                ['PMA_table']
            )
        );
    }

    /**
     * Test for makeConsistentWithList
     */
    public function testMakeConsistentWithList(): void
    {
        $GLOBALS['dbi']->expects($this->any())
            ->method('fetchResult')
            ->will(
                $this->returnValue($this->columnData)
            );
        $GLOBALS['dbi']->expects($this->any())
            ->method('fetchValue')
            ->will(
                $this->returnValue('PMA_table=CREATE table `PMA_table` (id integer)')
            );
        $this->assertTrue(
            $this->centralColumns->makeConsistentWithList(
                'phpmyadmin',
                ['PMA_table']
            )
        );
    }

    /**
     * Test for getFromTable
     */
    public function testGetFromTable(): void
    {
        $db = 'PMA_db';
        $table = 'PMA_table';

        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                'SELECT col_name FROM `pma_central_columns` '
                . "WHERE db_name = 'PMA_db' AND col_name IN ('id','col1','col2');",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(['id', 'col1'])
            );
        $this->assertEquals(
            [
                'id',
                'col1',
            ],
            $this->centralColumns->getFromTable(
                $db,
                $table
            )
        );
    }

    /**
     * Test for getFromTable with $allFields = true
     */
    public function testGetFromTableWithAllFields(): void
    {
        $db = 'PMA_db';
        $table = 'PMA_table';

        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` '
                . "WHERE db_name = 'PMA_db' AND col_name IN ('id','col1','col2');",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array_slice($this->columnData, 0, 2))
            );
        $this->assertEquals(
            array_slice($this->modifiedColumnData, 0, 2),
            $this->centralColumns->getFromTable(
                $db,
                $table,
                true
            )
        );
    }

    /**
     * Test for updateOneColumn
     */
    public function testUpdateOneColumn(): void
    {
        $this->assertTrue(
            $this->centralColumns->updateOneColumn(
                'phpmyadmin',
                '',
                '',
                '',
                '',
                '',
                0,
                '',
                '',
                ''
            )
        );
        $this->assertTrue(
            $this->centralColumns->updateOneColumn(
                'phpmyadmin',
                'col1',
                '',
                '',
                '',
                '',
                0,
                '',
                '',
                ''
            )
        );
    }

    /**
     * Test for updateMultipleColumn
     */
    public function testUpdateMultipleColumn(): void
    {
        $params = [];
        $params['db'] = 'phpmyadmin';
        $params['orig_col_name'] = [
            'col1',
            'col2',
        ];
        $params['field_name'] = [
            'col1',
            'col2',
        ];
        $params['field_default_type'] = [
            '',
            '',
        ];
        $params['col_extra'] = [
            '',
            '',
        ];
        $params['field_length'] = [
            '',
            '',
        ];
        $params['field_attribute'] = [
            '',
            '',
        ];
        $params['field_type'] = [
            '',
            '',
        ];
        $params['field_collation'] = [
            '',
            '',
        ];
        $this->assertTrue(
            $this->centralColumns->updateMultipleColumn($params)
        );
    }

    /**
     * Test for getHtmlForEditingPage
     */
    public function testGetHtmlForEditingPage(): void
    {
        $GLOBALS['dbi']->expects($this->any())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` '
                . "WHERE db_name = 'phpmyadmin' AND col_name IN ('col1','col2');",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue($this->columnData)
            );
        $result = $this->centralColumns->getHtmlForEditingPage(
            [
                'col1',
                'col2',
            ],
            'phpmyadmin'
        );
        $this->assertStringContainsString(
            '<form',
            $result
        );
        $header_cells = [
            __('Name'),
            __('Type'),
            __('Length/Values'),
            __('Default'),
            __('Collation'),
            __('Attributes'),
            __('Null'),
            __('A_I'),
        ];
        $this->assertStringContainsString(
            $this->callFunction(
                $this->centralColumns,
                CentralColumns::class,
                'getEditTableHeader',
                [$header_cells]
            ),
            $result
        );
        $list_detail_cols = $this->callFunction(
            $this->centralColumns,
            CentralColumns::class,
            'findExistingColNames',
            [
                'phpmyadmin',
                "'col1','col2'",
                true,
            ]
        );
        $this->assertStringContainsString(
            $this->callFunction(
                $this->centralColumns,
                CentralColumns::class,
                'getHtmlForEditTableRow',
                [
                    $list_detail_cols[0],
                    0,
                ]
            ),
            $result
        );
        $this->assertStringContainsString(
            $this->callFunction($this->centralColumns, CentralColumns::class, 'getEditTableFooter', []),
            $result
        );
    }

    /**
     * Test for getListRaw
     */
    public function testGetListRaw(): void
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` '
                . "WHERE db_name = 'phpmyadmin';",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue($this->columnData)
            );
        $this->assertEquals(
            $this->modifiedColumnData,
            $this->centralColumns->getListRaw(
                'phpmyadmin',
                ''
            )
        );
    }

    /**
     * Test for getListRaw with a table name
     */
    public function testGetListRawWithTable(): void
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` '
                . "WHERE db_name = 'phpmyadmin' AND col_name "
                . "NOT IN ('id','col1','col2');",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue($this->columnData)
            );
        $this->assertEquals(
            $this->modifiedColumnData,
            $this->centralColumns->getListRaw(
                'phpmyadmin',
                'table1'
            )
        );
    }

    /**
     * Test for getHtmlForMain
     */
    public function testGetHtmlForMain(): void
    {
        $db = 'phpmyadmin';
        $total_rows = 50;
        $pos = 26;
        $pmaThemeImage = 'pmaThemeImage';
        $text_dir = 'text_dir';
        $max_rows = (int) $GLOBALS['cfg']['MaxRows'];
        // test for not empty table
        $result = $this->centralColumns->getHtmlForMain(
            $db,
            $total_rows,
            $pos,
            $pmaThemeImage,
            $text_dir
        );
        $this->assertStringContainsString(
            '<form action="index.php?route=/database/central-columns',
            $result
        );
        $this->assertStringContainsString(
            '" method="post">',
            $result
        );
        $this->assertStringContainsString(
            Url::getHiddenInputs(
                'phpmyadmin'
            ),
            $result
        );
        $this->assertStringContainsString(
            '<input class="btn btn-secondary ajax" type="submit" name="navig" value="&lt">',
            $result
        );
        $this->assertStringContainsString(
            Util::pageselector(
                'pos',
                $max_rows,
                ($pos / $max_rows) + 1,
                (int) ceil($total_rows / $max_rows)
            ),
            $result
        );
        $this->assertStringContainsString('<span>+', $result);
        $this->assertStringContainsString('class="new_central_col hide"', $result);
        $this->assertStringContainsString(__('Filter rows') . ':', $result);
        $this->assertStringContainsString(__('Add column'), $result);
        $this->assertStringContainsString(__('Click to sort.'), $result);
        $this->assertStringContainsString(Url::getHiddenInputs($db), $result);
        $this->assertStringContainsString(Url::getHiddenInputs($db), $result);
        $editSelectedButton = '            <button class="btn btn-link mult_submit change_central_columns"'
            . ' type="submit" name="edit_central_columns"' . "\n"
            . '                    value="edit central columns" title="' . __('Edit') . '">' . "\n"
            . '                ' . Generator::getIcon('b_edit', __('Edit')) . "\n"
            . '            </button>' . "\n";

        $deleteSelectedButton = '            <button class="btn btn-link mult_submit" type="submit"'
            . ' name="delete_central_columns"' . "\n"
            . '                    value="remove_from_central_columns" title="' . __('Delete') . '">' . "\n"
            . '                ' . Generator::getIcon('b_drop', __('Delete')) . "\n"
            . '            </button>' . "\n";

        $this->assertStringContainsString($editSelectedButton, $result);
        $this->assertStringContainsString($deleteSelectedButton, $result);
        // test for empty table
        $total_rows = 0;
        $result = $this->centralColumns->getHtmlForMain(
            $db,
            $total_rows,
            $pos,
            $pmaThemeImage,
            $text_dir
        );
        $this->assertStringContainsString('<span>-', $result);
        $this->assertStringContainsString('class="new_central_col"', $result);
        $this->assertStringContainsString(__('Add column'), $result);
        $this->assertStringContainsString(Url::getHiddenInputs($db), $result);
        $this->assertStringContainsString(__('The central list of columns for the current database is empty'), $result);
    }

    /**
     * Test for configErrorMessage
     */
    public function testConfigErrorMessage(): void
    {
        $this->assertInstanceOf(
            Message::class,
            $this->callFunction($this->centralColumns, CentralColumns::class, 'configErrorMessage', [])
        );
    }

    /**
     * Test for findExistingColNames
     */
    public function testFindExistingColNames(): void
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                "SELECT * FROM `pma_central_columns` WHERE db_name = 'phpmyadmin'"
                . " AND col_name IN ('col1');",
                null,
                null,
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array_slice($this->columnData, 1, 1))
            );
        $this->assertEquals(
            array_slice($this->modifiedColumnData, 1, 1),
            $this->callFunction(
                $this->centralColumns,
                CentralColumns::class,
                'findExistingColNames',
                [
                    'phpmyadmin',
                    "'col1'",
                    true,
                ]
            )
        );
    }

    /**
     * Test for getTableFooter
     */
    public function testGetTableFooter(): void
    {
        $pmaThemeImage = 'pmaThemeImage';
        $text_dir = 'text_dir';
        $result = $this->centralColumns->getTableFooter($pmaThemeImage, $text_dir);
        $this->assertStringContainsString(
            '<input type="checkbox" id="tableslistcontainer_checkall" class="checkall_box"',
            $result
        );
        $this->assertStringContainsString('With selected:', $result);
        $this->assertStringContainsString(
            '<button class="btn btn-link mult_submit change_central_columns"',
            $result
        );
    }

    /**
     * Test for getHtmlForColumnDropdown
     */
    public function testGetHtmlForColumnDropdown(): void
    {
        $db = 'PMA_db';
        $selected_tbl = 'PMA_table';
        $result = $this->centralColumns->getHtmlForColumnDropdown(
            $db,
            $selected_tbl
        );
        $this->assertEquals(
            '<option value="id">id</option><option value="col1">col1</option>'
            . '<option value="col2">col2</option>',
            $result
        );
    }
}
