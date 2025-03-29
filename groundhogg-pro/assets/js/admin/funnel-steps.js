( ($) => {

  const {
    select,
    input,
    inputRepeater,
    loadingModal,
    toggle,
    dialog,
    inputWithReplacements,
    bold,
  } = Groundhogg.element

  const {
    metaPicker,
  } = Groundhogg.pickers

  const { post, get } = Groundhogg.api

  const { sprintf, __, _x, _n } = wp.i18n

  const EditMeta = {
    edit: ({ meta, data }) => {

      const {
        changes = [],
      } = meta

      return [
        `<p><b>${ __('Meta Changes') }</b></p>`,
        `<div class="changes"></div>`,
        '<p></p>',
      ].join('')

    },
    onMount: ({ ID, meta = {} }, updateStepMeta) => {

      let id = `step_${ ID }_edit_meta_settings`

      let {
        changes = [],
      } = meta

      if (!Array.isArray(changes)) {
        changes = []
      }

      inputRepeater(`#${ id } .changes`, {
        rows: changes,
        sortable: true,
        cells: [
          (props) => input({
            placeholder: __('Meta'),
            className: 'input meta-picker',
            ...props,
          }),
          ({ value, ...props }) => select({
            ...props,
            selected: value,
            style: {
              width: 'auto',
            },
            options: {
              set: 'Set',
              add: 'Add',
              subtract: 'Subtract',
              multiply: 'Multiply',
              divide: 'Divide',
              delete: 'Delete',
            },
          }),
          (props, [key, opp, val]) => input({
            placeholder: __('Value'),
            readonly: opp === 'delete',
            ...props,
          }),
        ],
        onMount: () => {
          metaPicker(`#${ id } .meta-picker`)

          $(`#${ id } select`).on('change', e => {
            if (e.target.value === 'delete') {
              e.target.nextElementSibling.setAttribute('readonly', true)
            }
            else {
              e.target.nextElementSibling.removeAttribute('readonly')
            }
          })
        },
        onChange: (rows) => {

          changes = rows

          updateStepMeta({
            changes,
          })
        },
      }).mount()
    },
  }

  const WebhookListener = {
    edit: ({ meta, data }) => {

      const {
        body = [],
        response_type = 'none',
      } = meta

      return [
        select({
          name: 'response_type',
          selected: response_type,
          options: {
            none: 'No Response',
            contact: 'The Contact Record',
            json: 'Custom JSON',
          },
        }),
        response_type === 'json' ? `<p><b>${ __('Response Body') }</b></p>` : '',
        response_type === 'json' ? `<div class="body"></div>` : '',
        '<p></p>',
      ].join('')

    },
    onMount: ({ ID, meta }, updateStepMeta) => {

      let id = `step_${ ID }_webhook_listener_settings`

      let {
        body = [],
        response_type = 'none',
      } = meta

      $(`#${ id } [name=response_type]`).on('change', e => {
        updateStepMeta({
          response_type: e.target.value,
        }, true)
      })

      if (response_type === 'json') {
        inputRepeater(`#${ id } .body`, {
          rows: body ?? [],
          sortable: true,
          cells: [
            input,
            input,
          ],
          onChange: (rows) => {

            body = rows

            updateStepMeta({
              body,
            })
          },
        }).mount()
      }
    },
  }

  const Webhook = {

    edit: ({ meta, data }) => {

      const {
        headers = [],
        body = [],
        method = 'post',
        post_url = '',
        content_type = '',
      } = meta

      return [
        `<p><b>${ __('Target URL') }</b></p>`,
        // language=HTML
        `
            <div class="gh-input-group">
                ${ select({
                    name: 'method',
                    selected: method,
                    options: {
                        post: 'POST',
                        put: 'PUT',
                        patch: 'PATCH',
                        get: 'GET',
                        delete: 'DELETE',
                    },
                }) }
                ${ input({
                    name: 'url',
                    className: 'full-width',
                    value: post_url,
                }) }
                <button class="test gh-button secondary">${ __('Send Test') }</button>
            </div>`,
        `<p><b>${ __('Request Headers') }</b></p>`,
        `<div class="headers"></div>`,
        method !== 'get' ? `<p><b>${ __('Payload Format') }</b></p>` : '',
        method !== 'get' ? select({
          name: 'content_type',
          selected: content_type,
          options: {
            form: 'x-www-form-urlencoded',
            json: 'JSON',
          },
        }) : '',
        `<p><b>${ __('Payload') }</b></p>`,
        `<div class="payload"></div>`,
      ].join('')

    },
    onMount: ({ ID, meta }, updateStepMeta) => {

      let id = `step_${ ID }_webhook_settings`

      let {
        post_keys = [],
        post_values = [],
        header_keys = [],
        header_values = [],
        headers = [],
        body = [],
      } = meta

      if (!headers.length && header_keys.length) {
        headers = header_keys.map((key, i) => ( [key, header_values[i]] ))
        updateStepMeta({ headers })
      }

      if (!body.length && post_keys.length) {
        body = post_keys.map((key, i) => ( [key, post_values[i]] ))
        updateStepMeta({ body })
      }

      $(`#${ id } .test`).on('click', e => {

        let { close } = loadingModal()

        post(`${ Groundhogg.api.routes.v4.root }/webhooks/send-test`, Funnel.getActiveStep()).then(r => {

          let { response } = r
          let { code } = response

          if (code >= 400) {
            dialog({
              type: 'error',
              message: `Something went wrong. Received a <b>${ code }</b> error response.`,
            })
            return
          }

          dialog({
            message: `Test sent! Received a ${ code } response.`,
          })

        }).catch(e => {

          dialog({
            type: 'error',
            message: e.message,
          })
        }).finally(() => {
          close()
        })
      })

      $(`#${ id } [name=method]`).on('change', e => {
        updateStepMeta({
          method: e.target.value,
        }, true)
      })

      $(`#${ id } [name=content_type]`).on('change', e => {
        updateStepMeta({
          content_type: e.target.value,
        })
      })

      $(`#${ id } [name=url]`).on('input change', e => {
        updateStepMeta({
          post_url: e.target.value,
        })
      })

      inputRepeater(`#${ id } .headers`, {
        rows: headers ?? [],
        sortable: true,
        cells: [
          (props) => input({
            placeholder: __('Header'),
            ...props,
          }),
          (props) => input({
            placeholder: __('Value'),
            ...props,
          }),
        ],
        onChange: (rows) => {

          headers = rows

          updateStepMeta({
            headers,
          })
        },
      }).mount()

      inputRepeater(`#${ id } .payload`, {
        rows: body ?? [],
        sortable: true,
        cells: [
          (props) => input({
            placeholder: __('Key'),
            ...props,
          }),
          (props) => input({
            placeholder: __('Value'),
            ...props,
          }),
        ],
        onChange: (rows) => {

          body = rows

          updateStepMeta({
            body,
          })
        },
      }).mount()

    },

  }

  const FieldTimer = {
    edit: ({ meta, data }) => {

      const {
        delay_amount = 3,
        delay_type = 'days',
        run_when,
        run_time,
        date_field = '',
        before_or_after = 'before',
        run_in_local_tz,
      } = meta

      const settings = []

      settings.push(`<p>${ __('Wait until...') }</p>`)

      const inputs = [
        input({
          name: 'delay_amount',
          className: 'number',
          type: 'number',
          value: delay_amount,
          disabled: delay_type === 'no_delay',
        }),
        select({
          name: 'delay_type',
          options: {
            no_delay: 'No Delay',
            minutes: 'Minutes',
            hours: 'Hours',
            days: 'Days',
            weeks: 'Weeks',
            months: 'Months',
          },
          selected: delay_type,
        }),
        select({
          name: 'before_or_after',
          options: {
            before: 'Before',
            after: 'After',
          },
          selected: before_or_after,
          disabled: delay_type === 'no_delay',
        }),
      ]

      settings.push(`<div class="gh-input-group">${ inputs.join('') }</div>`)

      settings.push(`<p>${ __('Custom date field or replacement code...') }</p>`)
      settings.push(inputWithReplacements({
        placeholder: __('Start typing...'),
        name: 'date_field',
        value: date_field,
        className: 'input',
      }))

      settings.push(`<p>${ __('Then run...') }</p>`)

      const inputs2 = [
        select({
          name: 'run_when',
          options: {
            now: 'Immediately',
            later: 'At a specified time',
          },
          selected: run_when,
        }),
      ]

      if (run_when === 'later') {
        inputs2.push(input({
          className: 'input',
          name: 'run_time',
          type: 'time',
          value: run_time,
        }))
      }

      settings.push(`<div class="gh-input-group">${ inputs2.join('') }</div>`)

      settings.push(`<div class="display-flex align-center gap-10">
                  <p>${ __('Run in the contact\'s timezone?', 'groundhogg') }</p>
                  ${ toggle({
        onLabel: 'Yes',
        offLabel: 'No',
        name: 'run_in_local_tz',
        checked: Boolean(run_in_local_tz),
      }) }
              </div>`)

      return settings.join('')

    },
    onMount: ({ ID, meta }, updateStepMeta) => {

      let id = `step_${ ID }_field_timer_settings`

      $(`#${ id } input[name='delay_amount'],
      #${ id } select[name='before_or_after'],
      #${ id } input[name='run_time']`).on('change', e => {
        updateStepMeta({
          [e.target.name]: e.target.value,
        })
      })

      $(`#${ id } input[name='run_in_local_tz']`).on('change', e => {
        updateStepMeta({
          run_in_local_tz: e.target.checked,
        })
      })

      $(`#${ id } select[name='delay_type'],
      #${ id } select[name='run_when']`).on('change', e => {
        updateStepMeta({
          [e.target.name]: e.target.value,
        }, true)
      })

      metaPicker(`#${ id } input[name='date_field']`).on('change', e => {
        updateStepMeta({
          date_field: e.target.value,
        })
      })

    },
  }

  const CustomActivity = {
    edit: ({ ID, meta, data }) => {

      const {
        conditions = [],
        type = '',
      } = meta

      return [
        `<p>${ __('When this type of activity is tracked...') }</p>`,
        input({
          id: `${ ID }-type`,
          value: type,
          className: 'code regular-text',
        }),
        `<p>${ __('And matches these conditions...') }</p>`,
        `<div id="${ ID }-conditions"></div>`,
        '<p></p>',
      ].join('')

    },
    onMount: ({ ID, meta }, updateStepMeta) => {

      let {
        conditions = [],
      } = meta

      $(`#${ ID }-type`).on('change', e => {

        let type = e.target.value

        updateStepMeta({
          type: type.toLowerCase().replaceAll(' ', '_'),
        }, true)
      })

      inputRepeater(`#${ ID }-conditions`, {
        rows: conditions ?? [],
        sortable: true,
        cells: [
          (props) => input({
            placeholder: __('key'),
            className: 'input',
            ...props,
          }),
          ({ value, ...props }) => select({
            ...props,
            selected: value,
            style: {
              width: 'auto',
            },
            options: {
              equals: _x('Equals', 'comparison', 'groundhogg'),
              not_equals: _x('Not equals', 'comparison', 'groundhogg'),
              contains: _x('Contains', 'comparison', 'groundhogg'),
              not_contains: _x('Does not contain', 'comparison', 'groundhogg'),
              starts_with: _x('Starts with', 'comparison', 'groundhogg'),
              ends_with: _x('Ends with', 'comparison', 'groundhogg'),
              does_not_start_with: _x('Does not start with', 'comparison', 'groundhogg'),
              does_not_end_with: _x('Does not end with', 'comparison', 'groundhogg'),
              less_than: _x('Less than', 'comparison', 'groundhogg'),
              less_than_or_equal_to: _x('Less than or equal to', 'comparison', 'groundhogg'),
              greater_than: _x('Greater than', 'comparison', 'groundhogg'),
              greater_than_or_equal_to: _x('Greater than or equal to', 'comparison', 'groundhogg'),
              empty: _x('Is empty', 'comparison', 'groundhogg'),
              not_empty: _x('Is not empty', 'comparison', 'groundhogg'),
            },
          }),
          ({ value, ...props }, [key, comp, val]) => input({
            placeholder: __('value'),
            readonly: ['empty', 'not_empty'].includes(comp),
            value: ['empty', 'not_empty'].includes(comp) ? '' : value,
            ...props,
          }),
        ],
        onMount: () => {
          $(`#${ ID }-conditions select`).on('change', e => {
            if (['empty', 'not_empty'].includes(e.target.value)) {
              e.target.nextElementSibling.setAttribute('readonly', ['empty', 'not_empty'].includes(e.target.value))
            }
            else {
              e.target.nextElementSibling.removeAttribute('readonly')
            }
          })
        },
        onChange: (rows) => {

          conditions = rows

          updateStepMeta({
            conditions,
          })
        },
      }).mount()
    },
  }

  const NewCustomActivity = {
    edit: ({ ID, meta, data }) => {

      const {
        value = '',
        type = '',
        details = [],
      } = meta

      return [
        `<p>${ __('The type of activity to track...') }</p>`,
        input({
          id: `${ ID }-type`,
          value: type,
          className: 'code regular-text',
          placeholder: 'my_custom_activity',
        }),
        `<p>${ __('Value for the activity...') }</p>`,
        inputWithReplacements({
          id: `${ ID }-value`,
          value: value,
          className: 'input',
          placeholder: 'No value',
        }),
        `<p>${ __('Additional meta details in key/value pairs.') }</p>`,
        `<div id="${ ID }-details"></div>`,
        '<p></p>',
      ].join('')

    },
    onMount: ({ ID, meta }, updateStepMeta) => {

      let {
        value = '',
        type = '',
        details = [],
      } = meta

      $(`#${ ID }-type`).on('change', e => {

        type = e.target.value

        updateStepMeta({
          type: type.toLowerCase().replaceAll(' ', '_'),
        }, true)
      })

      $(`#${ ID }-value`).on('change', e => {

        value = e.target.value

        updateStepMeta({
          value,
        })
      })

      inputRepeater(`#${ ID }-details`, {
        rows: details ?? [],
        sortable: true,
        cells: [
          (props) => input({
            placeholder: __('key'),
            className: 'input',
            ...props,
          }),
          (props) => inputWithReplacements({
            placeholder: __('value'),
            className: 'input',
            ...props,
          }),
        ],
        onChange: (rows) => {

          details = rows

          updateStepMeta({
            details,
          })
        },
      }).mount()
    },
  }

  const renderComponent = (id, step, component) => {

    const mount = (step) => {
      $(`#${ id }`).html(component.edit(step))

      component.onMount(step, (meta, reRender) => {

        let step = Funnel.updateStepMeta(meta)

        if (reRender) {
          mount(step)
        }

      }, () => {}, () => Funnel.getActiveStep())
    }

    mount(step)

  }

  const FunnelSteps = {

    init () {

      $(document).on('step-active', e => {

        let active = Funnel.getActiveStep()

        switch (active.data.step_type) {
          case 'http_post':
            this.webhook(active)
            break
          case 'webhook_listener':
            this.webhookListener(active)
            break
          case 'edit_meta':
            this.editMeta(active)
            break
          case 'field_timer':
            this.fieldTimer(active)
            break
          case 'custom_activity':
            this.customActivity(active)
            break
          case 'new_custom_activity':
            this.newCustomActivity(active)
            break
        }
      })
    },

    webhook (step) {
      let id = `step_${ step.ID }_webhook_settings`
      renderComponent(id, step, Webhook)
    },

    editMeta (step) {
      let id = `step_${ step.ID }_edit_meta_settings`
      renderComponent(id, step, EditMeta)
    },

    webhookListener (step) {
      let id = `step_${ step.ID }_webhook_listener_settings`
      renderComponent(id, step, WebhookListener)
    },

    fieldTimer (step) {
      let id = `step_${ step.ID }_field_timer_settings`
      renderComponent(id, step, FieldTimer)
    },

    customActivity (step) {
      let id = `step_${ step.ID }_custom_activity`
      renderComponent(id, step, CustomActivity)
    },

    newCustomActivity (step) {
      let id = `step_${ step.ID }_new_custom_activity`
      renderComponent(id, step, NewCustomActivity)
    },
  }

  FunnelSteps.init()

  // const { Filters, FilterRegistry } = Groundhogg.filters
  const { Select, Fragment, Div, ItemPicker } = MakeEl

  const {
    searches: SearchesStore,
  } = Groundhogg.stores

  const { post_types: PostTypes } = _BlockEditor

  const { user: { getOwner } } = Groundhogg

  Funnel.registerStepCallbacks('apply_owner', {
    onActive: ({ ID, meta }) => {

      let picker = document.getElementById(`step_${ ID }_owners`)

      let { owner_id = [] } = meta

      if (!owner_id) {
        owner_id = []
      } else {
        // make sure all selected owners exist
        owner_id = owner_id.filter( id => getOwner( id ) )
      }

      if (picker) {

        picker.replaceWith(ItemPicker({
          id: `step-owners-${ ID }`,
          noneSelected: 'Select an owner...',
          selected: owner_id.map(user_id => {

            let { ID, data: { display_name, user_email } } = getOwner( user_id )

            return {
              id: ID,
              text: `${ display_name } (${ user_email })`,
            }
          }),
          fetchOptions: async search => {
            let regexp = new RegExp(search, 'i')
            return Groundhogg.filters.owners.filter(user => {
              return user.data.display_name.match(regexp) || user.data.user_email.match(regexp)
            }).
              map(({ ID, data: { display_name, user_email } }) => ( {
                id: ID,
                text: `${ display_name } (${ user_email })`,
              } ))
          },
          onChange: items => Funnel.updateStepMeta({
            owner_id: items.map(({ id }) => id),
          }),
        }))
      }

    },
  })

  Funnel.registerStepCallbacks('post_published', {
    onActive: async ({ ID, meta = {}, data = {}, updateStep }) => {

      let container = document.querySelector(`#settings-${ ID } .gh-panel .custom-settings`)

      let { post_type = 'post', search_method = 'marketable-contacts' } = meta

      if (!post_type) {
        post_type = 'post'
        updateStep({
          post_type,
        })
      }

      const morph = () => morphdom(container, Div({}, Settings()), {
        childrenOnly: true,
      })

      await SearchesStore.maybeFetchItems()

      const searchMethods = [
        {
          id: 'all-contacts',
          text: __('All contacts.', 'groundhogg'),
        },
        {
          id: 'marketable-contacts',
          text: __('All marketable contacts.', 'groundhogg'),
        },
        {
          id: 'confirmed-contacts',
          text: __('All confirmed contacts.', 'groundhogg'),
        },
        ...SearchesStore.getItems().map(({ id, name }) => ( {
          id,
          text: sprintf(__('Saved search %s', 'groundhogg'), bold(name)),
        } )),
      ]

      const Settings = () => {

        const postType = PostTypes[post_type]

        const taxonomies = postType.taxonomies || {}

        const pickers = []

        for (let tax in taxonomies) {

          const taxonomy = taxonomies[tax]

          if (!taxonomy.show_in_rest || !taxonomy.public) {
            continue
          }

          const terms = meta[tax] ?? []

          pickers.push(
            `<p>${ sprintf(__('And %s has any of the following %s...'), postType.labels.singular_name.toLowerCase(),
              bold(taxonomy.label.toLowerCase())) }</p>`)
          pickers.push(ItemPicker({
            id: `post-${ tax }-${ ID }`,
            selected: terms,
            tags: false,
            fetchOptions: async (search) => {
              let terms = await get(`${ Groundhogg.api.routes.wp.v2 }/${ taxonomy.rest_base || tax }/`, {
                search,
                per_page: 20,
                orderby: 'count',
                order: 'desc',
              })
              terms = terms.map(({ id, name }) => ( { id, text: name } ))
              return terms
            },
            onChange: selected => {
              meta[tax] = selected
              updateStep({
                [tax]: selected,
              })
            },
          }))
        }

        return Fragment([
          `<p>${ __('When this post type is published...') }</p>`,
          Select({
            name: 'post_type',
            options: Object.keys(PostTypes).map(type => ( { value: type, text: PostTypes[type].labels.name } )),
            selected: post_type,
            onChange: e => {
              post_type = e.target.value
              updateStep({
                post_type,
              })
              morph()
            },
          }),
          ...pickers,
          `<p>${ __('Then add these contacts to the funnel...', 'groundhogg') }</p>`,
          ItemPicker({
            id: `select-search-method-${ ID }`,
            multiple: false,
            clearable: false,
            selected: searchMethods.find(({ id }) => id === search_method),
            fetchOptions: async search => {
              return searchMethods.filter(({ text }) => text.match(new RegExp(search, 'i')))
            },
            onChange: (item) => {

              if (!item) {
                item = { id: 'marketable-contacts' }
              }

              search_method = item.id
              updateStep({
                search_method,
              })
            },
          }),
          `<p></p>`,
        ])
      }

      morph()

    },
  })

} )(jQuery)
