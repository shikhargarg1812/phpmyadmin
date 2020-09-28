<?php
/**
 * Holds the PhpMyAdmin\Controllers\Database\RoutinesController
 */

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Database;

use PhpMyAdmin\CheckUserPrivileges;
use PhpMyAdmin\Common;
use PhpMyAdmin\Database\Routines;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Response;
use PhpMyAdmin\Template;
use PhpMyAdmin\Util;
use function in_array;
use function strlen;

/**
 * Routines management.
 */
class RoutinesController extends AbstractController
{
    /** @var CheckUserPrivileges */
    private $checkUserPrivileges;

    /**
     * @param Response            $response            Response object
     * @param DatabaseInterface   $dbi                 DatabaseInterface object
     * @param Template            $template            Template object
     * @param string              $db                  Database name
     * @param CheckUserPrivileges $checkUserPrivileges CheckUserPrivileges object
     */
    public function __construct($response, $dbi, Template $template, $db, CheckUserPrivileges $checkUserPrivileges)
    {
        parent::__construct($response, $dbi, $template, $db);
        $this->checkUserPrivileges = $checkUserPrivileges;
    }

    public function index(): void
    {
        global $db, $table, $tables, $num_tables, $total_num_tables, $sub_part, $is_show_stats;
        global $db_is_system_schema, $tooltip_truename, $tooltip_aliasname, $pos;
        global $errors;

        $params = ['type' => $_REQUEST['type'] ?? null];

        $this->checkUserPrivileges->getPrivileges();

        if (! $this->response->isAjax()) {
            /**
             * Displays the header and tabs
             */
            if (! empty($table) && in_array($table, $this->dbi->getTables($db))) {
                Common::table();
            } else {
                $table = '';
                Common::database();

                [
                    $tables,
                    $num_tables,
                    $total_num_tables,
                    $sub_part,
                    $is_show_stats,
                    $db_is_system_schema,
                    $tooltip_truename,
                    $tooltip_aliasname,
                    $pos,
                ] = Util::getDbInfo($db, $sub_part ?? '');
            }
        } elseif (strlen($db) > 0) {
            $this->dbi->selectDb($db);
        }

        /**
         * Keep a list of errors that occurred while
         * processing an 'Add' or 'Edit' operation.
         */
        $errors = [];

        $routines = new Routines($this->dbi, $this->template, $this->response);
        $routines->main($params['type']);
    }
}
