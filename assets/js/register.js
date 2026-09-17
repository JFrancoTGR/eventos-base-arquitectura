(() => {
  'use strict';

  const form = document.getElementById('registration-form');

  if (!form) {
    return;
  }

  const guestsContainer = document.getElementById('guests-container');
  const addGuestButton = document.getElementById('add-guest');
  const submitButton = document.getElementById('submit-registration');

  const eventSlug = form.dataset.event;
  const maxGuests = Number(form.dataset.maxGuests || 0);

  let guestCount = 0;
  let isSending = false;

  function createGuest() {
    if (guestCount >= maxGuests) {
      return;
    }

    guestCount++;

    const position = guestCount;

    const wrapper = document.createElement('section');

    wrapper.className = 'dataContainer guestContainer attendee-guest';
    wrapper.dataset.guest = String(position);

    wrapper.innerHTML = `
    <p class="formSubtitle">
        Invitado ${position}
    </p>

    <input
        type="text"
        class="guest-first-name"
        placeholder="Nombre"
        autocomplete="given-name"
        required
    >

    <input
        type="text"
        class="guest-last-name"
        placeholder="Apellidos"
        autocomplete="family-name"
        required
    >

    <input
        type="email"
        class="guest-email"
        placeholder="Correo electrónico"
        autocomplete="email"
        required
    >

    <input
        type="tel"
        class="guest-phone"
        placeholder="Teléfono"
        autocomplete="tel"
        required
    >

    <button
        type="button"
        class="remove-guest"
    >
        Eliminar invitado
    </button>
`;

    guestsContainer.appendChild(wrapper);

    updateAddGuestButton();
  }

  function removeGuest(element) {
    element.remove();

    guestCount = guestsContainer.querySelectorAll('.attendee-guest').length;

    renumberGuests();
    updateAddGuestButton();
  }

  function renumberGuests() {
    const guests = guestsContainer.querySelectorAll('.attendee-guest');

    guests.forEach((guest, index) => {
      const position = index + 1;

      guest.dataset.guest = String(position);

      const title = guest.querySelector('.formSubtitle');

      if (title) {
        title.textContent = `Invitado ${position}`;
      }

      if (title) {
        title.textContent = `Invitado ${position}`;
      }
    });
  }

  function updateAddGuestButton() {
    if (!addGuestButton) {
      return;
    }

    addGuestButton.hidden = guestCount >= maxGuests;
  }

  function getUtmData() {
    const params = new URLSearchParams(window.location.search);

    return {
      source: params.get('utm_source'),
      medium: params.get('utm_medium'),
      campaign: params.get('utm_campaign'),
    };
  }

  function buildAttendees() {
    const attendees = [];

    attendees.push({
      first_name: document.getElementById('primary-first-name').value.trim(),

      last_name: document.getElementById('primary-last-name').value.trim(),

      email: document.getElementById('primary-email').value.trim(),

      phone: document.getElementById('primary-phone').value.trim(),
    });

    const guests = guestsContainer.querySelectorAll('.attendee-guest');

    guests.forEach((guest) => {
      attendees.push({
        first_name: guest.querySelector('.guest-first-name').value.trim(),

        last_name: guest.querySelector('.guest-last-name').value.trim(),

        email: guest.querySelector('.guest-email').value.trim(),

        phone: guest.querySelector('.guest-phone').value.trim(),
      });
    });

    return attendees;
  }

  async function submitRegistration() {
    if (isSending) {
      return;
    }

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const registrantType = form.querySelector(
      'input[name="registrant_type"]:checked',
    );

    const payload = {
      event: eventSlug,

      registrant_type: registrantType ? registrantType.value : null,

      attendees: buildAttendees(),

      utm: getUtmData(),

      website: document.getElementById('website').value,
    };

    isSending = true;
    submitButton.disabled = true;
    submitButton.textContent = 'Registrando...';

    try {
      const response = await fetch('api/register.php', {
        method: 'POST',

        headers: {
          'Content-Type': 'application/json',
        },

        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (!response.ok || !result.ok) {
        throw result;
      }

      form.reset();

      guestsContainer.innerHTML = '';
      guestCount = 0;

      updateAddGuestButton();

      await Swal.fire({
        icon: 'success',
        title: 'Registro confirmado',
        text:
          result.attendees === 1
            ? 'Tu asistencia ha sido registrada correctamente.'
            : `Se registraron correctamente ${result.attendees} asistentes.`,
        confirmButtonText: 'Aceptar',
      });
    } catch (error) {
      let message = 'No fue posible completar el registro. Intenta nuevamente.';

      if (error && error.message) {
        message = error.message;
      }

      await Swal.fire({
        icon: 'error',
        title: 'No pudimos completar el registro',
        text: message,
        confirmButtonText: 'Aceptar',
      });
    } finally {
      isSending = false;

      submitButton.disabled = false;
      submitButton.textContent = 'Registrar asistencia';
    }
  }

  if (addGuestButton) {
    addGuestButton.addEventListener('click', createGuest);
  }

  guestsContainer.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-guest');

    if (!button) {
      return;
    }

    const guest = button.closest('.attendee-guest');

    if (guest) {
      removeGuest(guest);
    }
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    submitRegistration();
  });

  updateAddGuestButton();
})();
