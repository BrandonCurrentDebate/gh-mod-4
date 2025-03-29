(($) => {

  const { Modal } = MakeEl

  const { emails:EmailsStore } = Groundhogg.stores
  const { loadingModal, bold } = Groundhogg.element

  const { __, sprintf } = wp.i18n

  $(document).on( 'click', '.resend-unopened', async e => {

    let broadcast_id, email_id

    broadcast_id = parseInt( e.currentTarget.dataset.broadcast )
    email_id = parseInt( e.currentTarget.dataset.email )

    let { close } = loadingModal()

    await EmailsStore.maybeFetchItem( email_id )
    await EmailsStore.maybeFetchItem( email_id )

    close()

    let email = EmailsStore.get( email_id )

    Modal({}, () => Groundhogg.BroadcastScheduler({
      object: EmailsStore.get( email_id ),
      searchMethod: 'resend',
      searchMethods: [{
        id: 'resend',
        text: sprintf( __( 'Send to recipients that did not open %s', 'groundhogg'), bold( email.data.title ) ),
        query: () => ({
          filters: [[{
            type: 'broadcast_received',
            status: 'complete',
            broadcast_id
          }]],
          exclude_filters: [[{
            type: 'broadcast_opened',
            broadcast_id
          }]]
        })
      }],
    }))

  } )

})(jQuery)
