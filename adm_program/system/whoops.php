<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'whoops.php')
{
    exit('This page may not be called directly!');
}


if ($gDebug)
{
    $run = new \Whoops\Run();

    if (\Whoops\Util\Misc::isCommandLine())
    {
        $handler = new \Whoops\Handler\PlainTextHandler($gLogger);
    }
    elseif (\Whoops\Util\Misc::isAjaxRequest())
    {
        $handler = new \Whoops\Handler\JsonResponseHandler();
        $handler->addTraceToOutput(true);
    }
    else
    {
        $handler = new \Whoops\Handler\PrettyPageHandler();
        $handler->setPageTitle('Admidio Exception/Error Info');

        $handler->addDataTable('Versions', array(
            'ADMIDIO_VERSION_TEXT' => ADMIDIO_VERSION_TEXT,
            'PHP_VERSION'          => PHP_VERSION
        ));
        $handler->addDataTable('Urls/Paths', array(
            'SCHEME'           => SCHEME,
            'HTTPS'            => HTTPS,
            'PORT'             => PORT,
            'HOST'             => HOST,
            'DOMAIN'           => DOMAIN,
            'ADMIDIO_URL_PATH' => ADMIDIO_URL_PATH,
            'ADMIDIO_URL'      => ADMIDIO_URL,
            'FILE_URL'         => FILE_URL,
            'CURRENT_URL'      => CURRENT_URL,
            'SERVER_PATH'      => SERVER_PATH,
            'ADMIDIO_PATH'     => ADMIDIO_PATH,
            'CURRENT_PATH'     => CURRENT_PATH
        ));
        $handler->addDataTable('Database', array(
            'DB_ENGINE'   => DB_ENGINE,
            'DB_HOST'     => DB_HOST,
            'DB_PORT'     => DB_PORT,
            'DB_NAME'     => DB_NAME,
            'DB_USERNAME' => DB_USERNAME
        ));
        $handler->addDataTable('Tables', array(
            'TABLE_PREFIX'            => TABLE_PREFIX,
            'TBL_ANNOUNCEMENTS'       => TBL_ANNOUNCEMENTS,
            'TBL_AUTO_LOGIN'          => TBL_AUTO_LOGIN,
            'TBL_CATEGORIES'          => TBL_CATEGORIES,
            'TBL_COMPONENTS'          => TBL_COMPONENTS,
            'TBL_DATES'               => TBL_DATES,
            'TBL_FILES'               => TBL_FILES,
            'TBL_FOLDERS'             => TBL_FOLDERS,
            'TBL_GUESTBOOK'           => TBL_GUESTBOOK,
            'TBL_GUESTBOOK_COMMENTS'  => TBL_GUESTBOOK_COMMENTS,
            'TBL_IDS'                 => TBL_IDS,
            'TBL_LINKS'               => TBL_LINKS,
            'TBL_LIST_COLUMNS'        => TBL_LIST_COLUMNS,
            'TBL_LISTS'               => TBL_LISTS,
            'TBL_MEMBERS'             => TBL_MEMBERS,
            'TBL_MENU'                => TBL_MENU,
            'TBL_MESSAGES'            => TBL_MESSAGES,
            'TBL_MESSAGES_CONTENT'    => TBL_MESSAGES_CONTENT,
            'TBL_ORGANIZATIONS'       => TBL_ORGANIZATIONS,
            'TBL_PHOTOS'              => TBL_PHOTOS,
            'TBL_PREFERENCES'         => TBL_PREFERENCES,
            'TBL_REGISTRATIONS'       => TBL_REGISTRATIONS,
            'TBL_ROLE_DEPENDENCIES'   => TBL_ROLE_DEPENDENCIES,
            'TBL_ROLES'               => TBL_ROLES,
            'TBL_ROLES_RIGHTS'        => TBL_ROLES_RIGHTS,
            'TBL_ROLES_RIGHTS_DATA'   => TBL_ROLES_RIGHTS_DATA,
            'TBL_ROOMS'               => TBL_ROOMS,
            'TBL_SESSIONS'            => TBL_SESSIONS,
            'TBL_TEXTS'               => TBL_TEXTS,
            'TBL_USERS'               => TBL_USERS,
            'TBL_USER_DATA'           => TBL_USER_DATA,
            'TBL_USER_FIELDS'         => TBL_USER_FIELDS,
            'TBL_USER_LOG'            => TBL_USER_LOG,
            'TBL_USER_RELATIONS'      => TBL_USER_RELATIONS,
            'TBL_USER_RELATION_TYPES' => TBL_USER_RELATION_TYPES
        ));
        $handler->addDataTable('Misc', array(
            'COOKIE_PREFIX' => COOKIE_PREFIX
        ));
    }

    $run->pushHandler($handler);

    $run->register();
}
