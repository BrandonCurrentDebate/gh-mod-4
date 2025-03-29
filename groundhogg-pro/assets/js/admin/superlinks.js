( () => {

  ( ($) => {

    const {
      modal,
      input,
      select,
      dialog,
      toggle,
      progressBar,
      loadingDots,
      icons,
      moreMenu,
      dangerConfirmationModal,
      loadingModal,
      inputRepeater,
      inputWithReplacements,
    } = Groundhogg.element

    const { superlinks: SuperlinksStore, tags: TagsStore } = Groundhogg.stores
    // const { createFilters } = Groundhogg.filters.functions
    const { post: apiPost } = Groundhogg.api
    const { metaPicker, tagPicker, linkPicker } = Groundhogg.pickers

    const { __, sprintf } = wp.i18n

    const { ajax } = Groundhogg.api

    $(() => {

      const deleteLink = (id) => {
        return SuperlinksStore.delete(id).then(() => {

          dialog({
            message: __('Superlink deleted!', 'groundhogg-lead-score'),
          })

          $(`#link-${ id }`).remove()
        })
      }

      const editLinkUI = (link) => {

        const { add_tags = [], remove_tags = [], data } = link
        const { name, meta_changes = [], target = '' } = data

        // language=HTML
        return `
            <div class="gh-header modal-header">
                <h3>${ __('Edit Superlink', 'groundhogg') }</h3>
                <div class="actions align-right-space-between">
                    <button class="gh-button dashicon no-border icon text gh-modal-button-close"><span
                            class="dashicons dashicons-no-alt"></span></button>
                </div>
            </div>
            <div class="gh-link-editor" style="width: 550px">
                <div class="gh-rows-and-columns">
                    <div class="gh-row">
                        <div class="gh-col" style="align-items: center">
                            <label
                                    for="link-title"><b>${ __('Superlink Name', 'groundhogg-pro') }</b></label>
                            ${ input({
                                id: 'link-name',
                                name: 'link_name',
                                value: name,
                            }) }
                        </div>
                    </div>
                    <div class="gh-row">
                        <div class="gh-col">
                            <label><b>${ __('Add these tags...', 'groundhogg-pro') }</b></label>
                            ${ select({ id: 'add-tags' }) }
                        </div>
                        <div class="gh-col">
                            <label><b>${ __('Remove these tags...', 'groundhogg-pro') }</b></label>
                            ${ select({ id: 'remove-tags' }) }
                        </div>
                    </div>
                    <div class="gh-row">
                        <div class="gh-col">
                            <label><b>${ __('Update these custom fields...', 'groundhogg-pro') }</b></label>
                            <div id="meta-changes"></div>
                        </div>
                    </div>
                    <div class="gh-row">
                        <div class="gh-col">
                            <label for="link-func"><b>${ __('Then redirect to...', 'groundhogg-pro') }</b></label>
                            ${ inputWithReplacements({
                                id: 'link-target',
                                name: 'target',
                                value: target,
                            }) }
                        </div>
                    </div>
                    <p></p>
                    <div class="align-right-space-between">
                        <button class="gh-button primary" id="save-link">${ __('Save changes') }</button>
                        <button class="gh-button secondary text icon" id="link-more">${ icons.verticalDots }</button>
                    </div>
                </div>
            </div>`
      }

      const editLinkUIOnMount = (link, {
        onDelete = () => {},
        onDuplicate = (link) => {},
      }) => {
        const { add_tags = [], remove_tags = [], data } = link
        const { meta_changes = [] } = data

        TagsStore.itemsFetched([...add_tags, ...remove_tags])

        let payload

        const clearPayload = () => {
          payload = {
            data: {
              ...data,
            },
          }
        }

        clearPayload()

        const updateLink = (data = {}) => {

          payload = {
            data: {
              ...payload.data,
              ...data,
            },
          }
        }

        tagPicker('#add-tags', true, items => { TagsStore.itemsFetched(items) }, {
          data: add_tags.map(tag => ( { id: tag.ID, text: tag.data.tag_name, selected: true } )),
          placeholder: 'Select tags...'
        }).on('change', e => {
          let tags = $(e.target).val()
          updateLink({
            tags,
          })
        })

        tagPicker('#remove-tags', true, items => { TagsStore.itemsFetched(items) }, {
          data: remove_tags.map(tag => ( { id: tag.ID, text: tag.data.tag_name, selected: true } )),
          placeholder: 'Select tags...'
        }).on('change', e => {
          let remove_tags = $(e.target).val()
          updateLink({
            remove_tags,
          })
        })

        linkPicker('#link-target').on('change', e => {
          updateLink({
            target: e.target.value,
          })
        })

        inputRepeater(`#meta-changes`, {
          rows: meta_changes ?? [],
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
            metaPicker(`#meta-changes .meta-picker`)

            $(`#meta-changes select`).on('change', e => {
              if (e.target.value === 'delete') {
                e.target.nextElementSibling.setAttribute('readonly', true)
              }
            })

          },
          onChange: (rows) => {
            updateLink({
              meta_changes: rows,
            })
          },
        }).mount()

        $('#link-name').on('change', e => {
          updateLink({
            name: e.target.value,
          })
        })

        $('#link-more').on('click', e => {
          moreMenu(e.target, {
            items: [
              {
                key: 'duplicate',
                text: __('Duplicate'),
              },
              {
                key: 'delete',
                text: `<span class="gh-text danger">${ __('Delete') }</span>`,
              },
            ],
            onSelect: k => {
              switch (k) {
                case 'duplicate':

                  SuperlinksStore.duplicate(link.ID, {
                    data: {
                      name: sprintf(__('Copy of %s', 'groundhogg'), link.data.name),
                    },
                  }).then((link) => {

                    dialog({
                      message: 'Superlink duplicated!',
                    })

                    ajax({
                      action: 'groundhogg_link_table_row',
                      link: link.ID,
                    }).then((r) => {

                      $(`#the-list`).prepend(r.data.row)

                    })

                    onDuplicate(link)
                  })

                  break
                case 'delete':

                  dangerConfirmationModal({
                    alert: `<p>${ __('Are you sure you want to delete this link?', 'groundhogg-lead-score') }</p>`,
                    onConfirm: () => {
                      deleteLink(link.ID).then(onDelete)
                    },
                  })

                  break
              }
            },
          })
        })

        $('#save-link').on('click', (e) => {

          let $btn = $(e.target)

          $btn.prop('disabled', true)
          $btn.text(__('Saving'))
          const { stop } = loadingDots('#save-link')

          SuperlinksStore.patch(link.ID, payload).then(() => {

            clearPayload()
            stop()
            $btn.prop('disabled', false)
            $btn.text(__('Save changes'))

            dialog({
              message: __('Link updated!', 'groundhogg-pro'),
            })

            ajax({
              action: 'groundhogg_link_table_row',
              link: link.ID,
            }).then((r) => {

              $(`#link-${ link.ID }`).replaceWith(r.data.row)
            })
          })
        })
      }

      $(document).on('click', '.delete-link', (e) => {
        e.preventDefault()

        const id = parseInt(e.currentTarget.dataset.id)

        dangerConfirmationModal({
          alert: `<p>${ __('Are you sure you want to delete this link?') }</p>`,
          onConfirm: () => {
            deleteLink(id)
          },
        })
      })

      const editLink = (r) => {
        modal({
          // dialogClasses: 'overflow-visible',
          content: editLinkUI(r),
          onOpen: ({ close }) => editLinkUIOnMount(r, {
            onDelete: close,
            onDuplicate: (r) => {
              close()
              editLink(r)
            },
          }),
        })
      }

      $(document).on('click', '.edit-link', (e) => {
        e.preventDefault()

        const id = parseInt(e.currentTarget.dataset.id)

        let link = SuperlinksStore.get(id)

        if (link) {
          editLink(link)
          return
        }

        const { close } = loadingModal()

        SuperlinksStore.fetchItem(id).then((link) => {
          close()
          editLink(link)
        })

      })

      $('#add-link').on('click', e => {
        e.preventDefault()

        const { setContent, close } = modal({
          // language=HTML
          content: `
              <div class="gh-header">
                  <h3>${ __('Add a new Superlink') }</h3>
                  <div class="actions align-right-space-between">
                      <button class="gh-button dashicon no-border icon text gh-modal-button-close"><span
                              class="dashicons dashicons-no-alt"></span></button>
                  </div>
              </div>
              <div id="new-link">
                  <div class="gh-input-group">
                      ${ input({
                          id: 'link-name',
                          name: 'link_name',
                          placeholder: __('Superlink name'),
                      }) }
                      <button class="gh-button primary" id="commit-link">${ __('Create link') }</button>
                  </div>
              </div>`,
          onOpen: ({ close, setContent }) => {

            let linkName = ''

            $('#link-name').focus().on('change', (e) => {
              linkName = e.target.value
            })

            $('#commit-link').on('click', (e) => {

              $(e.target).prop('disabled', true)
              const { stop } = loadingDots('#commit-link')

              SuperlinksStore.post({
                data: {
                  name: linkName,
                },
                force: true,
              }).then((link) => {

                stop()
                close()

                editLink(link)

                ajax({
                  action: 'groundhogg_link_table_row',
                  link: link.ID,
                }).then((r) => {

                  $(`#the-list`).prepend(r.data.row)

                })

              })

            })

          },
        })

      })

    })

  } )(jQuery)

} )()
