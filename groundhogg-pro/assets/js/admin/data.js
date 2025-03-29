(() => {
  const { createStore } = Groundhogg

  const { routes } = GroundhoggPro

  const SuperlinksStore = createStore('superlinks', routes.superlinks )
})()
