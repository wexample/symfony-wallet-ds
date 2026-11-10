<?php

namespace Wexample\SymfonyWalletDs\Service\FormProcessor\DataResolver;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Wexample\SymfonyForms\Service\FormProcessor\FormProcessorDataResolverInterface;
use Wexample\SymfonyWallet\Entity\AbstractCredential;
use Wexample\SymfonyWallet\Service\CredentialTypeRegistry;

/**
 * The credential a form edits: the one of the route's `id`, or a new one of
 * the route's `type` — its kind decides the form's fields before it is drawn.
 */
#[AutoconfigureTag('wexample.symfony_forms.form_processor_data_resolver')]
final readonly class CredentialFormDataResolver implements FormProcessorDataResolverInterface
{
    /**
     * @param class-string<AbstractCredential> $credentialClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CredentialTypeRegistry $typeRegistry,
        private string $credentialClass,
    ) {
    }

    public function resolve(Request $request, array $options = []): AbstractCredential
    {
        if ($id = $request->attributes->get('id')) {
            return $this->entityManager->getRepository($this->credentialClass)->find($id)
                ?? throw new NotFoundHttpException('No credential '.$id);
        }

        $type = (string) $request->attributes->get('type');
        if (! $this->typeRegistry->has($type)) {
            throw new NotFoundHttpException('No credential of kind '.$type);
        }

        $credential = new $this->credentialClass();
        $credential->setType($type);

        return $credential;
    }
}
