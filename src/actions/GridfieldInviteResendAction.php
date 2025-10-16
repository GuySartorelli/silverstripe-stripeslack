<?php

namespace Firesphere\StripeSlack\Actions;

use Firesphere\StripeSlack\Model\SlackInvite;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Manifest\VersionProvider;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * class GridfieldInviteResendAction adds the resend button to the CMS for easy re-inviting
 */
class GridfieldInviteResendAction extends AbstractGridFieldComponent implements GridField_ColumnProvider, GridField_ActionProvider
{

    /**
     * @param GridField $gridField
     * @param array $columns
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns, true)) {
            $columns[] = 'Actions';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'col-buttons'];
    }

    /**
     * {@inheritDoc}
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    /**
     * @param GridField $gridField
     * @param SlackInvite $record
     * @param string $columnName
     * @return DBHTMLText
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        $config = SiteConfig::current_site_config();
        // No point in showing the re-send button, if there's no token
        if ($config->SlackToken) {
            $field = GridField_FormAction::create(
                $gridField,
                'Resend' . $record->ID,
                false,
                'resend',
                ['RecordID' => $record->ID]
            )->addExtraClass('gridfield-button-resend btn btn-secondary btn--no-text');

            if (!$record->Invited) {
                $field
                    ->setAttribute('title', 'Retry invite')
                    ->setIcon('attention-1')
                    ->setDescription(_t('GridfieldInviteResendAction.Resend', 'Retry failed invitation'));
            } else {
                $field
                    ->setAttribute('title', 'Resend invite')
                    ->setIcon('sync')
                    ->setDescription('Resend invite');
            }

            $frameworkVersion = VersionProvider::singleton()->getModuleVersion('silverstripe/framework');
            $isSensibleVersion = preg_match('/^(?<version>[0-9]+(?:\.[0-9]+)?)(?:\.[0-9]+)?(?<dev>.x-dev)?$/', $frameworkVersion, $match);
            if ($isSensibleVersion) {
                if ($match['version'] < '6.2' && (!$match['dev'] || strlen($match['version']) > 1)) {
                    $field->addExtraClass('font-icon-' . $field->getIcon());
                }
            }

            return $field->Field();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getActions($gridField)
    {
        return ['resend'];
    }

    /**
     * @param GridField $gridField
     * @param $actionName
     * @param $arguments
     * @param $data
     * @throws ValidationException
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'resend') {
            /** @var SlackInvite $item */
            $item = SlackInvite::get()->byID($arguments['RecordID']);
            if (!$item) {
                return;
            }

            $result = $item->resendInvite();
            if ($result) {
                Controller::curr()->getResponse()->setStatusCode(
                    200,
                    'User successfully invited.'
                );
            } else {
                Controller::curr()->getResponse()->setStatusCode(
                    200,
                    $result
                );
            }
        }
    }
}
