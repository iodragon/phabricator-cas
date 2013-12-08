<?php
final class PhutilAuthAdapterCas extends PhutilAuthAdapter {

  private $emailDomain;

  public function getEmailDomain($domain){
    $this->emailDomain = $domain;
  }
  public function getProviderName() {
    return pht('CAS');
  }

  public function getDescriptionForCreate() {
    return pht(
      'Configure a connection to use CAS authentication '.
      'credentials to log in to Phabricator.');
  }

  public function getAdapterDomain() {
    return 'self';
  }

  public function getAdapterType() {
    return 'CAS';
  }

  public function getAccountID() {
    if (!class_exists('phpCAS')){
      require_once "CAS.php";
    }
    return phpCAS::getUser();
  }

  public function getAccountEmail() {
    if ($this->emailDomain){
      $domain = ltrim($this->emailDomain, '@');
      return $this->getAccountID() ."@$domain";
    } else {
      return parent::getAccountEmail();
    }
  }

  public function getAccountName() {
    return $this->getAccountID();
  }

}
