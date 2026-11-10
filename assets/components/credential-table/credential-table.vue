<script>
import AbstractPlatformEntityTable from '@wexample/symfony-platform-ds/components/collection/table/abstract-platform-entity-table/abstract-platform-entity-table.vue';
import Credential from '@wexample/symfony-wallet/Entity/Credential';

const T = 'WexampleSymfonyWalletDsBundle.components.credential-table.credential-table::';

// The tone of each outcome of a test (Credential::CHECK_STATUS_*).
const CHECK_TONES = {
  pending: 'running',
  ok: 'success',
  error: 'error'
};

// The credentials, as their administrators read and test them: searched,
// filtered by type, sorted and paged by the api. A test asked is read again
// until it is answered, its reason in the marker's tooltip.
export default {
  extends: AbstractPlatformEntityTable,

  template: '#vue-template-wexample-symfony-wallet-ds-bundle-components-credential-table-credential-table',

  props: {
    // The kinds of credential: value, label.
    types: {
      type: Array,
      required: true
    }
  },

  data() {
    const t = (key) => this.trans(`${T}${key}`);

    return {
      searchPlaceholder: t('search'),
      defaultSort: { key: 'title', direction: 'asc' },
      barFilters: [
        { key: 'type', label: t('column.type'), multiple: true, options: this.types.map((type) => ({ value: type.value, label: type.label })) }
      ]
    };
  },

  watch: {
    entities(entities) {
      if (entities.some((entity) => entity.checkStatus === 'pending')) {
        this.startCollectionPolling();
      } else {
        this.stopCollectionPolling();
      }
    }
  },

  methods: {
    getPollingIntervalMs() {
      return 2000;
    },

    getEntityClass() {
      return Credential;
    },

    getStateKey() {
      return 'credentials';
    },

    getColumnsConfiguration() {
      const t = (key) => this.trans(`${T}${key}`);

      return [
        { key: 'title', label: t('column.title'), sortable: true },
        { key: 'type', label: t('column.type'), align: 'center', sortable: true, format: (value, row) => row?.typeTitle ?? value },
        { key: 'target', label: t('column.target'), cell: 'code', secondary: true },
        {
          key: 'checkStatus',
          label: t('column.check'),
          align: 'center',
          cell: 'status',
          format: (value, row) => ({
            type: CHECK_TONES[value] ?? 'neutral',
            label: t(`check.${value ?? 'never'}`),
            title: row?.checkMessage ?? undefined
          })
        },
        { key: 'checkedAt', label: t('column.checked_at'), cell: 'date', sortable: true },
        { key: 'usage', label: t('column.usage'), cell: 'number', align: 'center', width: 's' },
        { key: 'actions', label: '', cell: 'actions', align: 'center' }
      ];
    }
  }
};
</script>
