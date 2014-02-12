<?php

namespace UniMapper\Extension\NetteExtension;

use Nette\Templating\FileTemplate,
    Nette\Diagnostics\IBarPanel,
    Nette\Latte\Engine,
    Nette\Object;

/**
 * Flexibee connection panel for Nette Framework.
 */
class FlexibeePanel extends Object implements IBarPanel
{

    /** @var \UniMapper\Connection\FlexibeeConnection $connection */
    private $connection;

    /**
     * Register panel.
     *
     * @param \UniMapper\Connection\FlexibeeConnection $connection
     *
     * @return void
     */
    public function __construct(\UniMapper\Connection\FlexibeeConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns HTML code for custom tab.
     *
     * @return string
     */
    public function getTab()
    {
        $responses = $this->connection->getResponses();
        if ($responses) {
            $template = new FileTemplate;
            $template->setFile(__DIR__ . "/tab.latte");
            $template->onPrepareFilters[] = function ($template) {
                    $template->registerFilter(new Engine);
                };
            $template->registerHelperLoader("\Nette\Templating\Helpers::loader");
            $template->responses = $responses;
            ob_start();
            echo $template->render();
            return ob_get_clean();
        }
    }

    /**
     * Returns HTML code for custom panel.
     *
     * @return string
     */
    public function getPanel()
    {
        $template = new FileTemplate;
        $template->setFile(__DIR__ . "/panel.latte");
        $template->onPrepareFilters[] = function ($template) {
                $template->registerFilter(new Engine);
            };
        $template->registerHelperLoader("\Nette\Templating\Helpers::loader");
        $template->responses = $this->connection->getResponses();
        ob_start();
        echo $template->render();
        return ob_get_clean();
    }

}