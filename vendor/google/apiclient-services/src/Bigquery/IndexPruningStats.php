<?php
/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Google\Service\Bigquery;

class IndexPruningStats extends \Google\Model
{
  protected $baseTableType = TableReference::class;
  protected $baseTableDataType = '';
  /**
   * @var string
   */
  public $postIndexPruningParallelInputCount;
  /**
   * @var string
   */
  public $preIndexPruningParallelInputCount;

  /**
   * @param TableReference
   */
  public function setBaseTable(TableReference $baseTable)
  {
    $this->baseTable = $baseTable;
  }
  /**
   * @return TableReference
   */
  public function getBaseTable()
  {
    return $this->baseTable;
  }
  /**
   * @param string
   */
  public function setPostIndexPruningParallelInputCount($postIndexPruningParallelInputCount)
  {
    $this->postIndexPruningParallelInputCount = $postIndexPruningParallelInputCount;
  }
  /**
   * @return string
   */
  public function getPostIndexPruningParallelInputCount()
  {
    return $this->postIndexPruningParallelInputCount;
  }
  /**
   * @param string
   */
  public function setPreIndexPruningParallelInputCount($preIndexPruningParallelInputCount)
  {
    $this->preIndexPruningParallelInputCount = $preIndexPruningParallelInputCount;
  }
  /**
   * @return string
   */
  public function getPreIndexPruningParallelInputCount()
  {
    return $this->preIndexPruningParallelInputCount;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(IndexPruningStats::class, 'Google_Service_Bigquery_IndexPruningStats');
