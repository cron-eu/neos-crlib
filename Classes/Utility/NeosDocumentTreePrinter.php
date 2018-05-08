<?php
/**
 * Created by PhpStorm.
 * User: remuslazar
 * Date: 02.05.18
 * Time: 22:12
 */

namespace CRON\CRLib\Utility;

use Neos\Flow\Cli\ConsoleOutput;
use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * @property int maxDepth
 * @property NodeInterface rootNode
 * @property ConsoleOutput consoleOutput
 */
class NeosDocumentTreePrinter
{
    public function __construct(NodeInterface $node, $maxDepth = 0) {
        $this->maxDepth = $maxDepth;
        $this->rootNode = $node;
    }

    private function trimPath($input) {
        return str_replace($this->rootNode->getPath(), '', $input);
    }

    /**
     * @param NodeInterface $document
     * @param int $currentDepth
     *
     * @param array $currentURLPathPrefix
     *
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    private function printDocument(NodeInterface $document, $currentDepth = 0, array $currentURLPathPrefix = [])
    {
        $urlPathPrefix = array_merge($currentURLPathPrefix, [$document->getProperty('uriPathSegment')]);
        $url = join('/', $urlPathPrefix);

        $this->consoleOutput->outputFormatted('%s "%s" {%s} [%s]', [
            str_replace('home', '', $url),
            $document->getProperty('title'),
            $document->getNodeType()->getName(),
            $this->trimPath($document->getPath()),
        ], $currentDepth * 0);

//        \Neos\Flow\var_dump($document);
        if ($currentDepth < $this->maxDepth) {
            $childDocuments = $document->getChildNodes('Neos.Neos:Document');
            foreach ($childDocuments as $childDocument) {
                $this->printDocument($childDocument, $currentDepth + 1, $urlPathPrefix);
            }

        } // bail out if we're over the configured depth limit
    }

    private $documentTree = [];

    /**
     * @param NodeInterface $document
     * @param int $currentDepth
     *
     * @param array $currentURLPathPrefix
     *
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    private function buildDocumentTreeRecursive(NodeInterface $document, $currentDepth = 0, array $currentURLPathPrefix = [])
    {
        $urlPathPrefix = array_merge($currentURLPathPrefix, [$document->getProperty('uriPathSegment')]);
        $url = join('/', $urlPathPrefix);

        $this->documentTree[] = [
            str_replace('home', '', $url),
            $document->getProperty('title'),
            $document->getNodeType()->getName(),
            $this->trimPath($document->getPath()),
        ];

        if ($currentDepth < $this->maxDepth) {
            $childDocuments = $document->getChildNodes('Neos.Neos:Document');
            foreach ($childDocuments as $childDocument) {
                $this->buildDocumentTreeRecursive($childDocument, $currentDepth + 1, $urlPathPrefix);
            }

        } // bail out if we're over the configured depth limit
    }

    /**
     * @param ConsoleOutput $output
     *
     * @param bool $asTable
     *
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    public function printTree(ConsoleOutput $output, $asTable = true)
    {
        $this->consoleOutput = $output;
        if ($asTable) {
            $this->documentTree = [];
            $this->buildDocumentTreeRecursive($this->rootNode);
            $this->consoleOutput->outputTable($this->documentTree, [
                'URL path',
                'Page Title',
                'Node Type',
                'Neos Node Path',
            ]);
        } else {
            $this->printDocument($this->rootNode);
        }
    }
}