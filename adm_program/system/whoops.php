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
    }

    $run->pushHandler($handler);

    $run->register();
}
