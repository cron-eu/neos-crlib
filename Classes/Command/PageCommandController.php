<?php
/**
 * Created by PhpStorm.
 * User: remuslazar
 * Date: 02.05.18
 * Time: 18:58
 */

namespace CRON\CRLib\Command;

use CRON\CRLib\Utility\NeosDocumentTreePrinter;
use CRON\CRLib\Utility\NeosDocumentWalker;
use /** @noinspection PhpUnusedAliasInspection */
    Neos\Flow\Annotations as Flow;

use Neos\Flow\Cli\CommandController;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Service\ContentContext;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\Workspace;

/**
 * Class PageCommandController
 *
 * This command controller offers some utilities like remove or batch change of existing Neos pages.
 *
 * The main difference to the existing NodeCommandController is that this one uses high level APIs to manage the underlying
 * nodes and will not use the (low level) doctrine methods to do so. By default it will also use the current workspace
 * of the "admin" user so all changes can be reviewed in the e.g. backend.
 *
 * @package CRON\CRLib\Command
 *
 * @property ContentContext context
 * @property string sitePath
 * @property string workspaceName
 * @property Site currentSite
 *
 * @Flow\Scope("singleton")
 */
class PageCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Service\ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var \Neos\Neos\Domain\Repository\SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @throws \Exception
     */
    public function initializeObject()
    {
        /** @var Site $currentSite */
        $currentSite = $this->siteRepository->findFirstOnline();
        if (!$currentSite) {
            throw new \Exception('No site found');
        }
        $this->sitePath = '/sites/' . $currentSite->getNodeName();
        $this->currentSite = $currentSite;
    }

    /**
     * Setup and configure the context to use, take care of the arguments like user name etc.
     *
     * @param string $user
     *
     * @throws \Exception
     */
    protected function setup($user=null)
    {
        // validate user name, use the live workspace if null
        $this->workspaceName = $user ? 'user-'.$user : 'live';

        /** @noinspection PhpUndefinedMethodInspection */
        if (!$this->workspaceRepository->findByName($this->workspaceName)->count()) {
            throw new \Exception(sprintf('Workspace "%s" is invalid', $this->workspaceName));
        }

        $this->context = $this->contextFactory->create([
            'workspaceName' => $this->workspaceName,
            'currentSite' => $this->currentSite,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);
    }

    /**
     * Shows the current configuration of the working environment
     *
     * @param string $user username to use, defaults to the admin user. This will also use the user's workspace by
     *     default for all operations.
     *
     * @throws \Exception
     */
    public function infoCommand($user = 'admin')
    {
        $this->setup($user);

        $this->output->outputTable(
            [
              ['Current Site Name', $this->currentSite->getName()],
              ['Workspace Name', $this->workspaceName],
              ['Site node name', $this->currentSite->getNodeName()],
            ],
            [ 'Key', 'Value']);
    }

    /**
     * Lists all documents, optionally filtered by a prefix
     *
     * @param string $user use this user's workspace
     * @param int $depth depth, defaults to 1
     * @param string $path , e.g. /news (don't use the /sites/dazsite prefix!)
     *
     */
    public function listCommand($user = 'admin', $depth=1, $path = '')
    {
        try {
            $this->setup($user);
            $rootNode = $this->context->getNode($this->sitePath . $path);
            if (!$rootNode) {
                throw new \Exception(sprintf('Could not find any node on path "%s"', $path));
            }
            $printer = new NeosDocumentTreePrinter($rootNode, $depth);
            $printer->printTree($this->output);
        } catch (\Exception $e) {
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
        }
    }

    /**
     * Remove documents, optionally filtered by a prefix. The unix return code will be 0 (successful) only if at least
     * one document was removed, else it will return 1. Useful for bash while loops.
     *
     * @param string $user use this user's workspace
     * @param string $path , e.g. /news (don't use the /sites/dazsite prefix!)
     * @param string $url use the URL instead of o path
     * @param int $limit limit
     *
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     */
    public function removeCommand($user = 'admin', $path = '', $url = '', $limit = 0)
    {
        try {
            $this->setup($user);

            if ($url) {
                $rootNode = $this->context->getNode($this->sitePath);
                $rootNode = $this->context->getNode($this->getNodePathForURL($rootNode, $url));
            } else {
                $rootNode = $this->context->getNode($this->sitePath . $path);
            }

            if (!$rootNode) {
                throw new \Exception(sprintf('Could not find any node on path "%s"', $path));
            }
            $walker = new NeosDocumentWalker($rootNode);

            /** @var NodeInterface[] $nodesToDelete */
            $nodesToDelete = $walker->getNodes($limit);

            foreach ($nodesToDelete as $nodeToDelete) {
                $nodeToDelete->remove();
            }

            $this->output->outputTable(array_map( function(NodeInterface $node) { return [$node]; }, $nodesToDelete),
                ['Deleted Pages']);
            $this->quit(count($nodesToDelete) > 0 ? 0 : 1);

        } catch (\Exception $e) {
            if ($e instanceof \Neos\Flow\Mvc\Exception\StopActionException) { return; }
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
            $this->quit(1);
        }
    }

    /**
     * Publish all pending changes in the workspace
     *
     * @param string $user use this user's workspace
     *
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     */
    public function publishCommand($user = 'admin')
    {
        try {
            $this->setup($user);
            $liveWorkspace = $this->workspaceRepository->findByIdentifier('live');
            if (!$liveWorkspace) {
                throw new \Exception('Could not find the live workspace.');
            }
            /** @var Workspace $liveWorkspace */
            $this->context->getWorkspace()->publish($liveWorkspace);
        } catch (\Exception $e) {
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
            $this->quit(1);
        }
    }

    /**
     * @param NodeInterface $document
     * @param $url
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getNodePathForURL(NodeInterface $document, $url) {
        $parts = explode('/', $url);
        foreach ($parts as $segment) {
            if (!$segment) { continue; }
            $document = $this->getChildDocumentByURIPathSegment($document, $segment);
        }

        return $document->getPath();
    }

    /**
     * @param NodeInterface $document
     * @param $pathSegment
     *
     * @return NodeInterface
     * @throws \Exception
     */
    private function getChildDocumentByURIPathSegment(NodeInterface $document, $pathSegment) {
        $found = array_filter($document->getChildNodes('Neos.Neos:Document'),
            function (NodeInterface $document) use ($pathSegment ){
                return $document->getProperty('uriPathSegment') === $pathSegment;
            }
        );

        if (count($found) === 0) {
            throw new \Exception(sprintf('Could not find any child document for URL path segment: "%s" on "%s',
                $pathSegment,
                $document->getPath()
            ));
        }
        return array_pop($found);
    }

    /**
     * Resolves a given URL to the current Neos node path
     *
     * @param string $url URL to resolve
     */
    public function resolveURLCommand($url)
    {
        try {
            $this->setup();

            /** @var NodeInterface $document */
            $document = $this->context->getNode($this->sitePath);
            $this->outputLine('%s', [$this->getNodePathForURL($document, $url)]);
        } catch (\Exception $e) {
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
        }
    }

}
