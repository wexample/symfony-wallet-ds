<?php

namespace Wexample\SymfonyWalletDs\Service\FormProcessor;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractEntityFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyWallet\Entity\AbstractCredential;
use Wexample\SymfonyWallet\Service\CredentialTypeRegistry;
use Wexample\SymfonyWalletDs\Controller\Pages\CredentialController;
use Wexample\SymfonyWalletDs\Form\CredentialForm;

/**
 * A credential saved by an administrator: its parameters written from their
 * fields — a secret left empty kept, any other emptied removed —, those its
 * type does not know left as they are. Changed, its last test no longer says
 * anything of it. Then back to the list, where it is tested.
 */
class CredentialFormProcessor extends AbstractEntityFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly CredentialTypeRegistry $typeRegistry,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public static function getFormClass(): string
    {
        return CredentialForm::class;
    }

    public function getRequiredRoles(): array
    {
        return [RoleHelper::ROLE_ADMIN];
    }

    protected function beforeSave(object $entity, FormInterface $form): void
    {
        /** @var AbstractCredential $entity */
        $type = (string) $entity->getType();

        foreach (CredentialForm::getParameterFields($form) as $name => $field) {
            $value = trim((string) $field->getData());

            if ('' === $value && $this->typeRegistry->isSecret($type, $name)) {
                continue;
            }

            $entity->setParameterValue($name, '' === $value ? null : $value);
        }

        if ($entity->isNew()) {
            $entity->setDateCreatedNow();
        }

        $entity->forgetCheck();
    }

    protected function afterSave(object $entity, FormInterface $form): void
    {
        parent::afterSave($entity, $form);

        $this->redirectToRoute(CredentialController::ROUTE_INDEX);
    }
}
