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

namespace Google\Service\OracleDatabase;

class PluggableDatabaseConnectionStrings extends \Google\Model
{
  /**
   * @var string[]
   */
  public $allConnectionStrings;
  /**
   * @var string
   */
  public $pdbDefault;
  /**
   * @var string
   */
  public $pdbIpDefault;

  /**
   * @param string[]
   */
  public function setAllConnectionStrings($allConnectionStrings)
  {
    $this->allConnectionStrings = $allConnectionStrings;
  }
  /**
   * @return string[]
   */
  public function getAllConnectionStrings()
  {
    return $this->allConnectionStrings;
  }
  /**
   * @param string
   */
  public function setPdbDefault($pdbDefault)
  {
    $this->pdbDefault = $pdbDefault;
  }
  /**
   * @return string
   */
  public function getPdbDefault()
  {
    return $this->pdbDefault;
  }
  /**
   * @param string
   */
  public function setPdbIpDefault($pdbIpDefault)
  {
    $this->pdbIpDefault = $pdbIpDefault;
  }
  /**
   * @return string
   */
  public function getPdbIpDefault()
  {
    return $this->pdbIpDefault;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(PluggableDatabaseConnectionStrings::class, 'Google_Service_OracleDatabase_PluggableDatabaseConnectionStrings');
