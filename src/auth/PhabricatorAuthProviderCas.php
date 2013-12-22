<?php
final class PhabricatorAuthProviderCas
  extends PhabricatorAuthProvider {

  private $adapter;
  const KEY_SITE = 'auth:cas:site';
  const KEY_VALIDATE = 'auth:cas:validate';
  const KEY_AUTO_LOGIN = 'auth:cas:auto_login';
  const KEY_EMAIL_DOMAIN = 'auth:cas:email_domain';

  public function getProviderName() {
    return pht('CAS login');
  }

  public function getDescriptionForCreate() {
    return pht(
      'Configure Phabricator to use your CAS '.
      'authentication as user credentials.');
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = new PhutilAuthAdapterCas();
      $adapter->getEmailDomain($this->getProviderConfig()->getProperty(self::KEY_EMAIL_DOMAIN));
      $this->adapter = $adapter;
    }
    return $this->adapter;
  }

  public function isLoginFormAButton() {
    return true;
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    $viewer = $request->getUser();

    if ($this->getProviderConfig()->getProperty(self::KEY_AUTO_LOGIN)){
        header("Location: ".$this->getLoginURI());
      return id(new AphrontRedirectResponse())->setURI($this->getLoginURI());
    }
    if ($mode == 'link') {
      $button_text = pht('Link External Account');
    } else if ($mode == 'refresh') {
      $button_text = pht('Refresh Account Link');
    } else if ($this->shouldAllowRegistration()) {
      $button_text = pht('Login or Register');
    } else {
      $button_text = pht('Login');
    }

    $icon = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_LOGIN)
      ->setSpriteIcon($this->getLoginIcon());

    $button = id(new PHUIButtonView())
        ->setSize(PHUIButtonView::BIG)
        ->setColor(PHUIButtonView::GREY)
        ->setIcon($icon)
        ->setText($button_text)
        ->setSubtext($this->getProviderName());

    $content = array($button);
    $uri = $this->getLoginURI();

    return phabricator_form(
      $viewer,
      array(
        'method' => 'GET',
        'action' => (string)$uri,
      ),
      $content);
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $properties = $this->readFormValuesFromProvider();
    $this->loadCas($properties);

    $request = $controller->getRequest();
    $adapter = $this->getAdapter();
    $account = null;
    $response = null;

    try {
      $account_id = $adapter->getAccountID();
    } catch (Exception $ex) {
      // TODO: Handle this in a more user-friendly way.
      throw $ex;
    }

    $request->setCookie('cas_login', 1);
    if (!strlen($account_id)) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The web server failed to provide an account ID.'));

      return array($account, $response);
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }

  private function getPropertyLabels() {
    return array(
      self::KEY_SITE => pht('CAS Server Url'),
      self::KEY_VALIDATE => pht('Validate CAS Server Token'),
      self::KEY_EMAIL_DOMAIN => pht('Domain used for email'),
      self::KEY_AUTO_LOGIN => pht('Auto Get Login info from CAS Server'),
    );
  }

  private function getPropertyKeys() {
    return array_keys($this->getPropertyLabels());
  }


  public function readFormValuesFromProvider() {
    $properties = array();
    foreach ($this->getPropertyKeys() as $key) {
      $properties[$key] = $this->getProviderConfig()->getProperty($key);
    }
    return $properties;
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    $values = array();
    foreach ($this->getPropertyKeys() as $key) {
      $values[$key] = $request->getStr($key);
    }

    return $values;
  }

  public function extendEditForm(
      AphrontRequest $request,
      AphrontFormView $form,
      array $values,
      array $issues) {

    parent::extendEditForm($request, $form, $values, $issues);
    $labels = $this->getPropertyLabels();

    $captions = array(
      self::KEY_SITE =>
        pht('CAS service url. Example: %s',
          phutil_tag('tt', array(), pht('https://cas.example.com'))),
      self::KEY_EMAIL_DOMAIN =>
        pht('Optional, left blank will require input email when create account or use [account id]@domain-you-typed as user email. Example: %s',
          phutil_tag('tt', array(), pht('example.com'))),
      self::KEY_AUTO_LOGIN =>
        pht('Default is true, will auto became logined when logined in cas server '),
      self::KEY_VALIDATE =>
        pht('Validate the token pass form the cas server'),
    );

    $types = array(
      self::KEY_AUTO_LOGIN         => 'checkbox',
      self::KEY_VALIDATE           => 'checkbox',
    );

    foreach ($labels as $key => $label) {
      $caption = idx($captions, $key);
      $type = idx($types, $key);
      $value = idx($values, $key);

      $control = null;
      switch ($type) {
        case 'checkbox':
          $control = id(new AphrontFormCheckboxControl())
            ->addCheckbox(
              $key,
              1,
              hsprintf('<strong>%s:</strong> %s', $label, $caption),
              $value);
          break;
        case 'list':
          $control = id(new AphrontFormTextControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value ? implode(', ', $value) : null);
          break;
        case 'password':
          $control = id(new AphrontFormPasswordControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value);
          break;
        default:
          $control = id(new AphrontFormTextControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value);
          break;
      }

      $form->appendChild($control);
    }
  }

  public function renderConfigPropertyTransactionTitle(
      PhabricatorAuthProviderConfigTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    $key = $xaction->getMetadataValue(
      PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);

    $labels = $this->getPropertyLabels();
    if (isset($labels[$key])) {
      $label = $labels[$key];

      if (!strlen($old)) {
        return pht(
          '%s set the "%s" value to "%s".',
          $xaction->renderHandleLink($author_phid),
          $label,
          $new);
      } else {
        return pht(
          '%s changed the "%s" value from "%s" to "%s".',
          $xaction->renderHandleLink($author_phid),
          $label,
          $old,
          $new);
      }
    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

  public function loadCas($config)
  {
    if (!class_exists('phpCAS', false)){
      require_once "CAS.php";
    }
    $site = idx($config, self::KEY_SITE);
    $url = parse_url($site);
    if (!$url){
      throw new UnexpectedValueException(self::KEY_SITE . " config is not valid [$site]");
    }
    $schema = idx($url,'schema','http');
    if (!isset($url['port'])){
      $port = $schema == 'http' ? 80 : 443;
    } else {
      $port = intval($url['port']);
    }
    phpCAS::client(CAS_VERSION_2_0, $url['host'], $port, '');
    phpCAS::setServerLoginURL($site."/login?service=".phpCAS::getServiceURL());
    phpCAS::setServerServiceValidateURL($site."/serviceValidate");
    phpCAS::setServerProxyValidateURL($site."/proxyValidate");
    phpCAS::handleLogoutRequests(false);
    if (!idx($config, self::KEY_VALIDATE)){
      phpCAS::setNoCasServerValidation();
    }
    phpCAS::forceAuthentication();
  }
}