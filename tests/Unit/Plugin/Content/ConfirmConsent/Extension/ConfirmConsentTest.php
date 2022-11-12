<?php

/**
 * @package     Joomla.UnitTest
 * @subpackage  Extension
 *
 * @copyright   (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Tests\Unit\Plugin\Content\ConfirmConsent\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Language;
use Joomla\Event\Dispatcher;
use Joomla\Plugin\Content\ConfirmConsent\Extension\ConfirmConsent;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Test class for ConfirmConsent plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  ConfirmConsent
 *
 * @testdox     The ConfirmConsent plugin
 *
 * @since       __DEPLOY_VERSION__
 */
class ConfirmConsentTest extends UnitTestCase
{
    /**
     * @testdox  that the consent field is loaded into the form
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testLoadConsentFieldInForm()
    {
        $form = new Form('com_contact.contact');

        $app = $this->createStub(CMSApplicationInterface::class);
        $app->method('getLanguage')->willReturn($this->createStub(Language::class));

        $dispatcher = new Dispatcher();
        $plugin     = new ConfirmConsent($dispatcher, ['params' => []]);
        $plugin->setApplication($app);
        $plugin->onContentPrepareForm($form, []);

        $this->assertNotFalse($form->getField('consentbox'));
    }

    /**
     * @testdox  that the consent field is not loaded into the form when wrong form name
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testLoadConsentFieldInFormWrongContext()
    {
        $form = new Form('invalid');
        $form->load('<form/>');

        $dispatcher = new Dispatcher();
        $plugin     = new ConfirmConsent($dispatcher, ['params' => []]);
        $plugin->setApplication($this->createStub(CMSApplicationInterface::class));
        $plugin->onContentPrepareForm($form, []);

        $this->assertFalse($form->getField('consentbox'));
    }

    /**
     * @testdox  that the consent field is not loaded into the form when wrong application
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function testLoadConsentFieldInFormWrongApplication()
    {
        $form = new Form('com_contact.contact');
        $form->load('<form/>');

        $app = $this->createStub(CMSApplicationInterface::class);
        $app->method('isClient')->willReturn(true);

        $dispatcher = new Dispatcher();
        $plugin     = new ConfirmConsent($dispatcher, ['params' => []]);
        $plugin->setApplication($app);
        $plugin->onContentPrepareForm($form, []);

        $this->assertFalse($form->getField('consentbox'));
    }
}
