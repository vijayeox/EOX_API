export const register = (core, desktop, options, metadata) => {

    desktop.on('theme:window:change', (win, name, value) => {
      if (name === 'minimized' && value === false) {
        win.state.styles.display = 'block';
        win._updateDOM();
      }
    });
  
    desktop.on('theme:window:transitionend', (ev, win) => {
      if (ev.propertyName === 'opacity') {
        const {$element} = win;
        const css = window.getComputedStyle($element);
  
        win.state.styles.display = css.visibility === 'hidden'
          ? 'none'
          : 'block';
  
        win._updateDOM();
      }
    });
  };