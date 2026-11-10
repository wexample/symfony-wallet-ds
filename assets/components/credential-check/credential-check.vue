<script>
import AbstractEntityManipulatorVueMixin from '@wexample/symfony-design-system/js/Vue/AbstractEntityManipulatorVueMixin';
import Marker from '@wexample/symfony-design-system/components/marker/marker.vue';
import DateDisplay from '@wexample/symfony-design-system/components/date-display/date-display.vue';
import { unwrapApiEnvelope } from '@wexample/js-api-entity/Common/ApiEnvelope';
import ToastService from '@wexample/symfony-loader/js/Services/ToastService';
import Credential from '@wexample/symfony-wallet/Entity/Credential';

const T = 'WexampleSymfonyWalletDsBundle.components.credential-check.credential-check::';

// How often the outcome is read again while the credential is tested, and
// for how long: a connection given some seconds, a queue to wait in besides.
const WAIT_STEP = 1500;
const WAIT_LIMIT = 60 * 1000;

// The tone of each outcome of a test (Credential::CHECK_STATUS_*).
const TONES = {
  pending: 'running',
  ok: 'success',
  error: 'error'
};

// A credential's last test, said as a record's details — connected, or
// refused with why —, and tested again by the page's own action: the button
// its toolbar holds (`data-credential-check` naming the credential), asked
// (CredentialCheckAskedEvent), then read again until it is answered. On a
// storage's page, its credential's.
export default {
  template: '#vue-template-wexample-symfony-wallet-ds-bundle-components-credential-check-credential-check',

  mixins: [AbstractEntityManipulatorVueMixin],

  components: {
    DsMarker: Marker,
    DateDisplay
  },

  props: {
    credentialId: {
      type: String,
      required: true
    },
    status: {
      type: String,
      default: null
    },
    message: {
      type: String,
      default: null
    },
    checkedAt: {
      type: String,
      default: null
    }
  },

  data() {
    return {
      outcome: { status: this.status, message: this.message, checkedAt: this.checkedAt },
      testing: false
    };
  },

  mounted() {
    // The page's action, wherever its toolbar stands — over the page, or
    // under a panel's header.
    document.addEventListener('click', this.onActionClick);

    if (this.status === 'pending') {
      this.test(() => Promise.resolve());
    }
  },

  beforeUnmount() {
    document.removeEventListener('click', this.onActionClick);
  },

  computed: {
    tone() {
      return TONES[this.outcome.status] ?? 'neutral';
    }
  },

  methods: {
    t(key) {
      return this.trans(`${T}${key}`);
    },

    // The buttons testing this credential: held off while it is tested.
    actions() {
      return [...document.querySelectorAll(`[data-credential-check="${this.credentialId}"]`)];
    },

    onActionClick(event) {
      const action = event.target.closest?.(`[data-credential-check="${this.credentialId}"]`);

      if (action && !this.testing) {
        event.preventDefault();
        this.ask();
      }
    },

    ask() {
      const repository = this.getEntityRepository(Credential);

      return this.test(() => repository.post({ endpoint: `check/${this.credentialId}` }));
    },

    async test(ask) {
      const repository = this.getEntityRepository(Credential);
      this.testing = true;
      this.actions().forEach((action) => { action.disabled = true; action.classList.add('is-loading'); });
      this.outcome = { ...this.outcome, status: 'pending' };

      try {
        await ask();
        const until = Date.now() + WAIT_LIMIT;

        while (Date.now() < until) {
          await new Promise((resolve) => setTimeout(resolve, WAIT_STEP));
          const read = unwrapApiEnvelope(await repository.client.get({ path: repository.buildPath(`check/${this.credentialId}`) }).json());

          if (read.status !== 'pending') {
            this.outcome = read;

            return;
          }
        }

        throw new Error('No answer in time.');
      } catch (error) {
        this.outcome = { ...this.outcome, status: null };
        this.app.getServiceOrFail(ToastService).show({ type: 'error', message: this.t('no_answer') });
      } finally {
        this.testing = false;
        this.actions().forEach((action) => { action.disabled = false; action.classList.remove('is-loading'); });
      }
    }
  }
};
</script>
