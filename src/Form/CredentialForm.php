<?php

namespace Wexample\SymfonyWalletDs\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Wexample\SymfonyForms\Form\Type\PasswordInputType;
use Wexample\SymfonyForms\Form\Type\SelectInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;
use Wexample\SymfonyPlatformDs\Form\AbstractPlatformForm;
use Wexample\SymfonyWallet\Entity\AbstractCredential;
use Wexample\SymfonyWallet\Service\CredentialTypeRegistry;

/**
 * A credential: its title, then the parameters its type asks
 * (`wexample_symfony_wallet.types`), one field each. The type is chosen
 * before the form — the address of a new one says it
 * (CredentialFormDataResolver) — and kept. A secret is never shown again:
 * left empty, the one saved stays.
 */
class CredentialForm extends AbstractPlatformForm
{
    private const string PARAMETER_FIELD_PREFIX = 'parameter_';

    /**
     * @param class-string<AbstractCredential> $credentialClass
     */
    public function __construct(
        private readonly CredentialTypeRegistry $typeRegistry,
        private readonly string $credentialClass,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => $this->credentialClass,
        ]);
    }

    protected function buildFields(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add('title', TextInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            self::FIELD_OPTION_NAME_REQUIRED => true,
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var AbstractCredential|null $credential */
            $credential = $event->getData();

            if ($type = $credential?->getType()) {
                $this->addParameterFields($event->getForm(), $type, $credential);
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            /** @var AbstractCredential|null $credential */
            $credential = $event->getData();
            $type = $this->typeRegistry->get((string) $credential?->getType());

            foreach (self::getParameterFields($event->getForm()) as $name => $field) {
                if (! $type?->isSecret($name)) {
                    $field->setData($credential?->getParameterValue($name) ?? $type?->getParameter($name)?->default);
                }
            }
        });
    }

    private function addParameterFields(FormInterface $form, string $type, AbstractCredential $credential): void
    {
        foreach ($this->typeRegistry->get($type)?->parameters ?? [] as $name => $parameter) {
            $fieldOptions = [
                self::FIELD_OPTION_NAME_LABEL => $parameter->getLabel(),
                self::FIELD_OPTION_NAME_MAPPED => false,
                // A secret saved before is kept when left empty; a default
                // fills one left out.
                self::FIELD_OPTION_NAME_REQUIRED => $parameter->required
                    && null === $parameter->default
                    && ! ($parameter->secret && $credential->getParameterValue($name)),
            ];

            if ($parameter->secret) {
                $form->add(self::PARAMETER_FIELD_PREFIX.$name, PasswordInputType::class, $fieldOptions + [
                    'help' => $credential->getParameterValue($name) ? '@form::field.secret.help' : null,
                    'always_empty' => true,
                ]);
            } elseif ($parameter->choices) {
                $form->add(self::PARAMETER_FIELD_PREFIX.$name, SelectInputType::class, $fieldOptions + [
                    'choices' => $parameter->choices,
                    'auto_translate_choices' => false,
                    'placeholder' => null === $parameter->default ? '' : null,
                ]);
            } else {
                $form->add(self::PARAMETER_FIELD_PREFIX.$name, TextInputType::class, $fieldOptions);
            }
        }
    }

    /** @return array<string, FormInterface> The parameters' fields, by parameter name. */
    public static function getParameterFields(FormInterface $form): array
    {
        $fields = [];

        foreach ($form as $name => $field) {
            if (str_starts_with($name, self::PARAMETER_FIELD_PREFIX)) {
                $fields[substr($name, strlen(self::PARAMETER_FIELD_PREFIX))] = $field;
            }
        }

        return $fields;
    }
}
