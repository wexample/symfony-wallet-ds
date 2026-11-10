<?php

namespace Wexample\SymfonyWalletDs\Controller\Pages;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\SymfonyForms\Attribute\EntityFormProcessor;
use Wexample\SymfonyForms\Service\FormProcessor\Notification;
use Wexample\SymfonyHelpers\Attribute\RouteDescription;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyWallet\Entity\AbstractCredential;
use Wexample\SymfonyWallet\Service\CredentialCheckService;
use Wexample\SymfonyWallet\Service\CredentialTypeRegistry;
use Wexample\SymfonyWallet\Service\CredentialUsageService;
use Wexample\SymfonyWalletDs\Service\FormProcessor\CredentialFormProcessor;
use Wexample\SymfonyWalletDs\Service\FormProcessor\DataResolver\CredentialFormDataResolver;
use Wexample\SymfonyWalletDs\Traits\SymfonyWalletDsBundleClassTrait;

/**
 * The credentials, for the administrators: listed, added by type, set,
 * tested — whatever opens the connection answers on the credential —, and
 * removed when nothing names them.
 */
#[Route(path: '/credentials/')]
#[IsGranted(RoleHelper::ROLE_ADMIN)]
final class CredentialController extends AbstractPagesController
{
    use SymfonyWalletDsBundleClassTrait;

    final public const string ROUTE_INDEX = 'credential_index';

    final public const string ROUTE_CREATE = 'credential_create';

    final public const string ROUTE_EDIT = 'credential_edit';

    final public const string ROUTE_CHECK = 'credential_check';

    final public const string ROUTE_DELETE = 'credential_delete';

    final public const string CSRF_CHECK = 'credential_check';

    final public const string CSRF_DELETE = 'credential_delete';

    private const string TRANSLATION_DOMAIN = 'WexampleSymfonyWalletDsBundle.pages.credential.index::';

    /**
     * @param class-string<AbstractCredential> $credentialClass
     */
    public function __construct(
        private readonly string $credentialClass,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[RouteDescription('The credentials, with their last test, to search, sort and act on.')]
    #[Route(path: '', name: self::ROUTE_INDEX)]
    public function index(CredentialTypeRegistry $typeRegistry): Response
    {
        return $this->renderPage('index', [
            'credential_types' => self::getTypeChoices($typeRegistry),
        ]);
    }

    /** A new credential of the type chosen in the list's menu: each asks its own parameters. */
    #[EntityFormProcessor(CredentialFormProcessor::class, AbstractCredential::class, formArgumentName: 'credentialForm', formDataResolverClass: CredentialFormDataResolver::class)]
    #[RouteDescription('Adds a credential of the type given.')]
    #[Route(path: 'create/{type}', name: self::ROUTE_CREATE)]
    public function create(FormInterface $credentialForm, CredentialTypeRegistry $typeRegistry): Response
    {
        /** @var AbstractCredential $credential */
        $credential = $credentialForm->getData();

        return $this->renderPage('create', [
            'form_create' => $credentialForm->createView(),
            'type_title' => $typeRegistry->getLabel((string) $credential->getType()),
        ]);
    }

    #[EntityFormProcessor(CredentialFormProcessor::class, AbstractCredential::class, formArgumentName: 'credentialForm', formDataResolverClass: CredentialFormDataResolver::class)]
    #[RouteDescription('Edits one credential: its title and its parameters.')]
    #[Route(path: '{id}/edit', name: self::ROUTE_EDIT)]
    public function edit(FormInterface $credentialForm, CredentialTypeRegistry $typeRegistry): Response
    {
        /** @var AbstractCredential $credential */
        $credential = $credentialForm->getData();

        return $this->renderPage('edit', [
            'form_edit' => $credentialForm->createView(),
            'credential' => $credential,
            'type_title' => $typeRegistry->getLabel((string) $credential->getType()),
        ]);
    }

    /** Its test asked; the list says how it went once it is answered. */
    #[Route(path: '{id}/check', name: self::ROUTE_CHECK, methods: ['POST'])]
    public function check(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CredentialCheckService $checkService,
    ): RedirectResponse {
        if (! $this->isCsrfTokenValid(self::CSRF_CHECK, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $credential = $this->findCredential($id, $entityManager);
        $checkService->ask($credential);
        $this->notify(Notification::TYPE_SUCCESS, 'check.asked', ['%title%' => $credential->getTitle()]);

        return $this->redirectToRoute(self::ROUTE_INDEX);
    }

    /** Removes a credential nothing names, then back to the list. */
    #[Route(path: '{id}/delete', name: self::ROUTE_DELETE, methods: ['POST'])]
    public function delete(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CredentialUsageService $usageService,
    ): RedirectResponse {
        if (! $this->isCsrfTokenValid(self::CSRF_DELETE, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $credential = $this->findCredential($id, $entityManager);

        if ($usageService->isUsed($credential)) {
            $this->notify(Notification::TYPE_WARNING, 'delete.kept', ['%title%' => $credential->getTitle()]);
        } else {
            $entityManager->remove($credential);
            $entityManager->flush();
            $this->notify(Notification::TYPE_SUCCESS, 'delete.done', ['%title%' => $credential->getTitle()]);
        }

        return $this->redirectToRoute(self::ROUTE_INDEX);
    }

    private function findCredential(string $id, EntityManagerInterface $entityManager): AbstractCredential
    {
        return $entityManager->getRepository($this->credentialClass)->find($id) ?? throw $this->createNotFoundException();
    }

    /** @return list<array{value: string, label: string, icon: string}> The types, for the list's menu and its filter. */
    private static function getTypeChoices(CredentialTypeRegistry $typeRegistry): array
    {
        return array_map(
            static fn (string $type): array => [
                'value' => $type,
                'label' => $typeRegistry->getLabel($type),
                'icon' => $typeRegistry->getIcon($type) ?? 'ph:bold/key',
            ],
            $typeRegistry->names()
        );
    }

    /** Said in a toast on the page the redirect reaches, once. */
    private function notify(string $type, string $key, array $parameters): void
    {
        $this->addFlash($type, (new Notification($this->translator->trans(self::TRANSLATION_DOMAIN.$key, $parameters), $type))->toArray());
    }
}
