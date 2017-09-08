<?php
declare(strict_types=1);

/**
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace Ytake\PrestoClient;

/**
 * Class QueryResult
 *
 * @author Yuuki Takezawa <yuuki.takezawa@comnect.jp.net>
 */
final class QueryResult
{
    /** @var string */
    private $id;

    /** @var string */
    private $infoUri;

    /** @var string */
    private $partialCancelUri;

    /** @var string */
    private $nextUri;

    /** @var \stdClass[] */
    private $columns = [];

    /** @var array */
    private $data = [];

    /** @var StatementStats|null */
    private $stats;

    /** @var QueryError|null */
    private $error;

    /**
     * QueryResult constructor.
     *
     * @param string $content
     */
    public function set(string $content)
    {
        $parsed = $this->parseContent($content);
        $this->id = $parsed->id;
        $this->infoUri = $parsed->infoUri;
        $this->partialCancelUri = $parsed->partialCancelUri ?? null;
        $this->nextUri = $parsed->nextUri ?? null;
        $this->columns = [];
        if (isset($parsed->columns)) {
            $this->columnTransfer($parsed->columns);
        }
        $this->data = $parsed->data ?? [];
        $this->stats = isset($parsed->stats) ? $this->statsTransfer($parsed->stats) : null;
        $this->error = isset($parsed->error) ? $this->errorTransfer($parsed->error) : null;
    }

    /**
     * @return string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getInfoUri()
    {
        return $this->infoUri;
    }

    /**
     * @return string|null
     */
    public function getNextUri()
    {
        return $this->nextUri;
    }

    /**
     * @return QueryError|null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return string|null
     */
    public function getPartialCancelUri()
    {
        return $this->partialCancelUri;
    }

    /**
     * @return \Generator
     */
    public function yieldData($asArray = false): \Generator
    {
        if (!count($this->data)) {
            yield;
        }
        $column = $this->getColumns();
        $columnNames = array_map(function ($item) {
            return $item->getName();
        }, $column);
        $columnCount = count($column);
        foreach ($this->data as $data) {
            if ($asArray) {
                yield array_combine($columnNames, $data);
            } else {
                $fixData = new FixData();
                for ($i = 0; $i < $columnCount; $i++) {
                    $fixData->add($column[$i]->getName(), $data[$i]);
                }
                yield $fixData;
            }
        }
    }

    /**
     * @param string $content
     *
     * @return \stdClass
     */
    private function parseContent(string $content): \stdClass
    {
        $parsed = json_decode($content);
        if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException;
        }

        return $parsed;
    }

    /**
     * @param \stdClass $jsonContent
     *
     * @return StatementStats
     */
    private function statsTransfer(\stdClass $jsonContent): StatementStats
    {
        return new StatementStats($jsonContent);
    }

    /**
     * @param \stdClass $jsonContent
     *
     * @return QueryError
     */
    private function errorTransfer(\stdClass $jsonContent): QueryError
    {
        return new QueryError($jsonContent);
    }

    /**
     * @param array $columns
     */
    private function columnTransfer(array $columns)
    {
        foreach ($columns as $column) {
            $this->columns[] = new Column($column);
        }
    }

    /**
     * @return StatementStats|null
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}
