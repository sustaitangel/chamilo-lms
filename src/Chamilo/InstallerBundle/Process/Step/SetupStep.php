<?php
/* For licensing terms, see /license.txt */

namespace Chamilo\InstallerBundle\Process\Step;

use Sylius\Bundle\FlowBundle\Process\Context\ProcessContextInterface;
use Chamilo\CoreBundle\Migrations\Data\ORM\LoadAdminUserData;
//use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Sylius\Bundle\SettingsBundle\Manager\SettingsManager;
use Symfony\Component\HttpFoundation\Response;
use Chamilo\UserBundle\Entity\User;

/**
 * Class SetupStep
 * @package Chamilo\InstallerBundle\Process\Step
 */
class SetupStep extends AbstractStep
{
    /**
     * @param ProcessContextInterface $context
     * @return Response
     */
    public function displayAction(ProcessContextInterface $context)
    {
        $form = $this->createSetupForm();

        $settingsManager = $this->get('chamilo.settings.manager');
        $settings = $settingsManager->loadSettings('platform');

        $form->get('portal')->get('institution')->setData($settings->get('institution'));
        $form->get('portal')->get('institution_url')->setData($settings->get('institution_url'));
        $form->get('portal')->get('site_name')->setData($settings->get('site_name'));
        $date = new \DateTime();
        $timezone = $date->getTimezone();
        $form->get('portal')->get('timezone')->setData($timezone->getName());

        return $this->render(
            'ChamiloInstallerBundle:Process/Step:setup.html.twig',
            array(
                'form' => $form->createView()
            )
        );
    }

    /**
     * @param ProcessContextInterface $context
     * @return null|\Sylius\Bundle\FlowBundle\Process\Step\ActionResult|Response
     */
    public function forwardAction(ProcessContextInterface $context)
    {
        $adminUser = $this
            ->getDoctrine()
            ->getRepository('ChamiloUserBundle:User')
            ->findOneBy(
                array('username' => LoadAdminUserData::DEFAULT_ADMIN_USERNAME)
            );

        if (!$adminUser) {
            throw new \RuntimeException("Admin user wasn't loaded in fixtures.");
        }

        $form = $this->createSetupForm();
        $form->get('admin')->setData($adminUser);

        $form->handleRequest($this->getRequest());

        if ($form->isValid()) {
            // pass "load demo fixtures" flag to the next step
            /*$context->getStorage()->set(
                'loadFixtures',
                $form->has('loadFixtures') && $form->get('loadFixtures')->getData()
            );*/
            $this->get('fos_user.user_manager')->updateUser($adminUser);

            // Setting portal parameters
            $settingsManager = $this->get('chamilo.settings.manager');
            $url = $this->get('chamilo_core.manager.access_url')->find(1);
            $settingsManager->setUrl($url);
            $settings = $settingsManager->loadSettings('platform');

            $parameters = array(
                'institution' => $form->get('portal')->get('institution')->getData(),
                'institution_url' => $form->get('portal')->get('institution_url')->getData(),
                'site_name' => $form->get('portal')->get('site_name')->getData(),
                'administrator_email' => $adminUser->getEmail(),
                'administrator_name' => $adminUser->getFirstName(),
                'administrator_surname' => $adminUser->getLastName(),
                'administrator_phone' => $adminUser->getPhone(),
                'timezone' => $form->get('portal')->get('timezone')->getData(),
            );
            $settings->setParameters($parameters);
            $settingsManager->saveSettings('platform', $settings);

            /*$defaultCompanyName  = $configManager->get('oro_ui.application_name');
            $defaultCompanyTitle = $configManager->get('oro_ui.application_title');
            $companyName         = $form->get('company_name')->getData();
            $companyTitle        = $form->get('company_title')->getData();
            if (!empty($companyName) && $companyName !== $defaultCompanyName) {
                $configManager->set('oro_ui.application_name', $companyName);
            }
            if (!empty($companyTitle) && $companyTitle !== $defaultCompanyTitle) {
                $configManager->set('oro_ui.application_title', $companyTitle);
            }
            $configManager->flush();
            */
            return $this->complete();
        }

        return $this->render(
            'ChamiloInstallerBundle:Process/Step:setup.html.twig',
            array(
                'form' => $form->createView()
            )
        );
    }

    /**
     * @return \Symfony\Component\Form\Form
     */
    protected function createSetupForm()
    {
        $data = $this->get('chamilo_installer.yaml_persister')->parse();

        return $this->createForm('chamilo_installer_setup', empty($data) ? null : $data);
    }
}
