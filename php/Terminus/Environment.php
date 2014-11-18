<?php
namespace Terminus;
use \ReflectionClass;

abstract class Environment {
  public $name = 'dev';
  public $site = false;
  public $diffstat;
  public $dns_zone;
  public $environment_created;
  public $lock;
  public $on_server_development;
  public $randseed;
  public $styx_cluster;
  public $target_commit;
  public $target_ref;
  public $watchers;
  public $backups;

  public function __construct( Site $site, $environment = null ) {
    $this->site = $site;

    if (is_object($environment)) {
      // if we receive an environment object from the api hydrate the vars
      $environment_properties = get_object_vars($environment);
      // iterate our local properties setting them where available in the imported object
      foreach (get_object_vars($this) as $key => $value) {
        if(array_key_exists($key,$environment_properties)) {
          $this->$key = $environment_properties[$key];
        }
      }
    }

  }

  public function wipe() {
    return \Terminus_Command::request('sites', $this->site->getId()->getName(), "environments/{$this->name}/wipe", 'POST');
  }

  public function diffstat() {
    return $this->diffstat;
  }

  /**
   * @param $element sting code,file,backup
   */
  public function backups($element = null) {
    if (null === $this->backups ) {
      $path = sprintf("environments/%s/backups/catalog", $this->name);
      $response = \Terminus_Command::request('sites', $this->site->getId(), $path, 'GET');
      $this->backups = $response['data'];
    }
    $backups = (array) $this->backups;
    if ($element) {
      foreach ($this->backups as $id => $backup) {
        if (!isset($backup->filename)) {
          unset($backups[$id]);
          continue;
        }
        if (!preg_match("#.*$element\.\w+\.gz$#", $backup->filename)) {
          unset($backups[$id]);
          continue;
        }
      }
    }
    return $backups;
  }

  /**
   * @param $bucket string -- backup folder
   * @param $element string -- files,code,database
   */
  public function backupUrl($bucket, $element) {
    $path = sprintf("environments/%s/backups/catalog/%s/%s/s3token", $this->name, $bucket, $element);
    $data = array('method'=>'GET');
    $options = array('body'=>json_encode($data), 'headers'=> array('Content-type'=>'application/json') );
    $response = \Terminus_Command::request('sites', $this->site->getId(), $path, 'POST', $options);
    return $response['data'];
  }

  /**
   * Start a work flow
   * @param $workflow string work flow "slot"
   */
  public function workflow($workflow) {
    $path = sprintf("environments/%s/workflows", $this->name);
    $data = array(
      'type' => $workflow,
      'environment' => $this->name,
    );
    $options = array('body'=>json_encode($data), 'headers'=> array('Content-type'=>'application/json'));
    $response = \Terminus_Command::request('sites', $this->site->getId(), $path, 'POST', $options);

    return $response['data'];
  }

  /**
   * Get the code log
   */
  public function log() {
    $path = sprintf("environments/%s/code-log",$this->name);
    $response = \Terminus_Command::request('sites', $this->site->getId(), $path, 'GET');
    return $response['data'];
  }

  /**
   * Lock environment
   */
  public function lock($username, $password) {
    $data = json_encode(array('username' => $username, 'password' => $password));
    $options = array(
      'body' => $data,
      'headers' => array('Content-type'=>'application/json')
    );
    $response = \Terminus_Command::request("sites", $this->site->getId(), 'environments/' . $this->name . '/lock', "PUT", $options);
    $response['data'];
  }

  /**
   * Delete an environment lock.
   */
  public function unlock() {
    $response = \Terminus_Command::request("sites", $this->site->getId(),  'environments/' . $this->name . '/lock', "DELETE");
    return $response['data'];
  }

  /**
   * Get Info on an environment lock
   */
  public function lockinfo() {
    $response = \Terminus_Command::request("sites", $this->site->getId(), 'environments/'.$this->name.'/lock', "GET");
    return $response['data'];
  }

  /**
   * list hotnames for environment
   */
  public function hostnames() {
    $response = \Terminus_Command::request("sites", $this->site->getId(), 'environments/' . $this->name . '/hostnames', 'GET');
    return $response['data'];
  }

  /**
   * Add hostname to environment
   */
  public function hostnameadd($hostname) {
    $response = \Terminus_Command::request("sites", $this->site->getId(), 'environments/' . $this->name . '/hostnames/' . rawurlencode($hostname), "PUT");
    return $response['data'];
  }

  /**
   * Delete hostname from environment
   */
  public function hostnamedelete($hostname) {
    $response = \Terminus_Command::request("sites", $this->site->getId(), 'environments/' . $this->name . '/hostnames/' . rawurlencode($hostname), "DELETE");
    return $response['data'];
  }

  /**
   * Generate environment URL
   */
  public function domain() {
    $host = sprintf( "%s-%s.%s", $this->name, $this->site->getName(), $this->dns_zone );
    return $host;
  }
}
