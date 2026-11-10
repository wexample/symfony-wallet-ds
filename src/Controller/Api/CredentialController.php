<?php

namespace Wexample\SymfonyWalletDs\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\CustomQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SearchQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SortQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyWallet\Entity\AbstractCredential;
use Wexample\SymfonyWallet\Service\CredentialCheckService;
use Wexample\SymfonyWallet\Service\CredentialPresenter;
use Wexample\SymfonyWallet\Service\CredentialUsageService;
use Wexample\SymfonyWalletDs\Controller\Pages\CredentialController as PagesCredentialController;

/**
 * The credentials, on the session, as their administrators read them: with
 * their last test, what names them and the actions their row offers; a test
 * asked from a page, and its outcome read until it is answered.
 */
#[Route(path: '_api/credential/', name: 'api_credential_')]
#[IsGranted(RoleHelper::ROLE_ADMIN)]
class CredentialController extends AbstractApiController
{
    final public const string ROUTE_LIST = 'list';

    final public const string ROUTE_CHECK = 'check';

    final public const string ROUTE_CHECK_ASK = 'check_ask';

    /**
     * @param class-string<AbstractCredential> $credentialClass
     */
    public function __construct(
        private readonly string $credentialClass,
    ) {
    }

    #[Route(path: self::ROUTE_LIST, name: self::ROUTE_LIST, methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET, options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    #[PageQueryOption]
    #[LengthQueryOption]
    #[SearchQueryOption]
    #[SortQueryOption(allowed: ['title', 'type', 'checkedAt'], default: 'title')]
    #[CustomQueryOption(key: 'type', constraint: new Type(type: VariableHelper::VARIABLE_TYPE_STRING))]
    public function list(
        Request $request,
        EntityManagerInterface $entityManager,
        CredentialPresenter $presenter,
        CredentialUsageService $usageService,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): ApiResponse {
        $builder = $entityManager->getRepository($this->credentialClass)->createQueryBuilder('credential');

        if ($search = trim((string) self::getQueryOptionValue($request, VariableHelper::SEARCH, ''))) {
            $builder
                ->andWhere('LOWER(credential.title) LIKE :search OR LOWER(credential.type) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        // Several types joined by commas.
        if ($types = array_filter(explode(',', (string) $request->query->get('type', '')))) {
            $builder->andWhere('credential.type IN (:types)')->setParameter('types', array_values($types));
        }

        return self::apiResponseQueryPage(
            $request,
            $builder,
            function (array $credentials) use ($presenter, $usageService, $csrfTokenManager): array {
                $usages = $usageService->countUsages($credentials);
                $actions = [];

                /** @var AbstractCredential $credential */
                foreach ($credentials as $credential) {
                    $id = (string) $credential->getId();

                    $actions[$id][] = [
                        'href' => $this->generateUrl(PagesCredentialController::ROUTE_CHECK, ['id' => $id]),
                        'method' => 'post',
                        'token' => $csrfTokenManager->getToken(PagesCredentialController::CSRF_CHECK)->getValue(),
                        'icon' => 'ph:bold/plugs-connected',
                        'label' => 'Test',
                    ];

                    if (0 === $usages[$id]) {
                        $actions[$id][] = [
                            'href' => $this->generateUrl(PagesCredentialController::ROUTE_DELETE, ['id' => $id]),
                            'method' => 'post',
                            'token' => $csrfTokenManager->getToken(PagesCredentialController::CSRF_DELETE)->getValue(),
                            'icon' => 'ph:bold/trash',
                            'label' => 'Delete',
                            'confirm' => [
                                'title' => 'Delete '.$credential->getTitle(),
                                'message' => 'Its parameters are lost; nothing uses it.',
                                'accept' => 'Delete',
                            ],
                        ];
                    }

                    $actions[$id][] = [
                        'href' => $this->generateUrl(PagesCredentialController::ROUTE_EDIT, ['id' => $id]),
                        'target' => 'panel',
                        'icon' => 'ph:bold/pencil-simple',
                        'label' => 'Edit',
                    ];
                }

                return $presenter->presentCollection($credentials, ['usages' => $usages, 'actions' => $actions]);
            },
        );
    }

    /** Its test asked; waited for by reading its outcome. */
    #[Route(path: self::ROUTE_CHECK.'/{id}', name: self::ROUTE_CHECK_ASK, requirements: ['id' => Requirement::UUID], methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_POST, options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    public function checkAsk(string $id, EntityManagerInterface $entityManager, CredentialCheckService $checkService): ApiResponse
    {
        $credential = $this->findCredential($id, $entityManager);
        $checkService->ask($credential);

        return self::apiResponseSuccess(data: $checkService->getOutcome($credential));
    }

    /** How its last test went, `pending` while it is not answered. */
    #[Route(path: self::ROUTE_CHECK.'/{id}', name: self::ROUTE_CHECK, requirements: ['id' => Requirement::UUID], methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET, options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    public function check(string $id, EntityManagerInterface $entityManager, CredentialCheckService $checkService): ApiResponse
    {
        return self::apiResponseSuccess(data: $checkService->getOutcome($this->findCredential($id, $entityManager)));
    }

    private function findCredential(string $id, EntityManagerInterface $entityManager): AbstractCredential
    {
        return $entityManager->getRepository($this->credentialClass)->find($id) ?? throw new NotFoundHttpException('No credential '.$id);
    }
}
