<?php

namespace Firesphere\StripeSlack\Form;

use Firesphere\StripeSlack\Controller\StripeSlackPageController;
use Firesphere\StripeSlack\Model\SlackInvite;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\SiteConfig\SiteConfig;

class SlackSignupForm extends Form
{
    private $siteConfig;

    /**
     * SlackSignupForm constructor.
     * @param Controller $controller
     * @param string $name
     * @param FieldList $fields
     * @param FieldList $actions
     * @param null $validator
     */
    public function __construct(
        ?Controller $controller = null,
        string $name = 'SlackSignupForm',
        ?FieldList $fields = null,
        ?FieldList $actions = null,
        $validator = null
    ) {
        $this->siteConfig = SiteConfig::current_site_config();
        if (!$controller) {
            $controller = Controller::curr();
        }
        if (!$fields) {
            $fields = $this->getFormFields();
        }
        if (!$actions) {
            $actions = $this->getFormActions();
        }

        parent::__construct($controller, $name, $fields, $actions, $validator);
    }

    /**
     * @return FieldList
     */
    protected function getFormFields()
    {
        if (!$this->siteConfig->SlackToken) {
            return FieldList::create([
                LiteralField::create(
                    'Setup',
                    _t('SlackSignupForm.Setup', 'StripeSlack has not yet been configured correctly')
                )
            ]);
        }
        $fields = FieldList::create(
            [
                LiteralField::create(
                    'Intro',
                    _t('SlackSignupForm.Intro', 'Fill out the form below to request access to Slack')
                ),
                TextField::create('Name', _t('SlackSignupForm.Name', 'My name is')),
                EmailField::create('Email', _t('SlackSignupForm.Email', 'My email address is'))
                    ->setAttribute('required', true),
            ]
        );

        $this->extend('updateFormFields', $fields);

        return $fields;
    }

    /**
     * @return FieldList
     */
    protected function getFormActions()
    {
        if (!$this->siteConfig->SlackToken) {
            return FieldList::create();
        }

        return FieldList::create([
            FormAction::create('submitSlackForm', _t('SlackSignupForm.Submit', 'Submit'))
        ]);
    }

    /**
     * @param array $data
     * @param SlackSignupForm $form
     */
    public function submitSlackForm($data, $form)
    {
        $signup = SlackInvite::create();
        $form->saveInto($signup);
        $userID = $signup->write();
        /** @var SlackInvite $signup We need to re-fetch from the database after writing */
        $signup = SlackInvite::get()->byID($userID);
        $this->redirectSlack($signup->Invited);
    }

    /**
     * This method seems long, but it's primarily switching between CMS and normal user
     * Plus a check if the URL's are set on the config.
     *
     * @param boolean $success
     * @return bool|HTTPResponse
     */
    public function redirectSlack($success)
    {
        $config = SiteConfig::current_site_config();
        if (!$success) {
            // Redirect to the failure page if there is one
            if ($config->SlackErrorBackURLID) {
                return $this->controller->redirect($config->SlackErrorBackURL()->Link());
            }

            // Use the failure template if we're using the right controller
            if ($this->controller instanceof StripeSlackPageController) {
                return $this->controller->redirect($this->controller->Link('oops'));
            }

            // Fall back to a standard message
            $this->sessionError(_t('SlackSignupForm.Failure', 'Failed to send invite. Please retry again.'));
            return $this->controller->redirectBack();
        }
        // Redirect to the success page if there is one
        if ($config->SlackBackURLID) {
            return $this->controller->redirect($config->SlackBackURL()->Link());
        }

        // Use the success template if we're using the right controller
        if ($this->controller instanceof StripeSlackPageController) {
            return $this->controller->redirect($this->controller->Link('yay'));
        }

        // Fall back to a standard message
        $this->sessionMessage(_t('SlackSignupForm.Success', 'Invite sent successfully.'), ValidationResult::TYPE_GOOD);
        return $this->controller->redirectBack();
    }
}
