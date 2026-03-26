(function ($) {
  const $modal = $('#psp-modal');
  const $steps = $('#psp-steps');
  const $open  = $('#psp-open');
  const $close = $('#psp-close');
  const $back  = $('#psp-back');
  const $form  = $('#psp-form');
  const $status = $('#psp-status');

  let transcript = [];
  let chosenTopic = null;

  const SUGGESTIONS = {
    login: [
      'Reset your password from the /my-account page (look for “Lost your password?”).',
      'If you see “link expired”, fully log out and retry in an incognito window.',
      'Still stuck? We’ll include diagnostics automatically.'
    ],
    payments: [
      'Check Orders under /my-account for payment status.',
      'If a payment failed, retry once; do not duplicate paid orders.',
      'Refunds for cancelled tutorials/webinars follow our policy.'
    ],
    webinars: [
      'After registering, your Zoom link appears in /my-events.',
      'You also receive a calendar .ics in your email confirmation.',
      'If the event moved, the /my-events link updates automatically.'
    ],
    tutorials: [
      'Pick a slot in the expert’s calendar and complete checkout.',
      'Changes/cancellations are managed from /my-tutorials.',
      'Experts can reschedule; you’ll receive a new .ics by email.'
    ],
    college: [
      'College classes are restricted to invited student lists.',
      'Your college coordinator shares the access link and timing.',
      'Missed it? Ask for the recap email after the session.'
    ],
    courses: [
      'Start or resume from the Courses page; progress is saved.',
      'Certificates are issued on completion and emailed to you.',
      'If access is missing, your subscription may have expired.'
    ],
    other: [
      'Tell us what you need. We’re happy to help.'
    ]
  };

  function go(step) {
    $steps.find('.psp-step').attr('hidden', true);
    $steps.find(`.psp-step[data-step="${step}"]`).attr('hidden', false);
  }

  function openModal() {
    $modal.attr('aria-hidden', 'false');
    go(1);
  }
  function closeModal() {
    $modal.attr('aria-hidden', 'true');
  }

  $open.on('click', openModal);
  $close.on('click', closeModal);
  $('#psp-done').on('click', closeModal);

  // Step 1 -> Step 2
  $('.psp-topics button').on('click', function () {
    chosenTopic = $(this).data('topic');
    transcript.push(`User selected topic: ${chosenTopic}`);
    const items = SUGGESTIONS[chosenTopic] || SUGGESTIONS.other;
    const html = items.map(i => `<li>${i}</li>`).join('');
    $('#psp-suggestions').html(`<ul>${html}</ul>`);
    go(2);
  });

  // Back to Step 1
  $back.on('click', () => {
    transcript.push('User navigated back to topics');
    go(1);
  });

  // Step 2 -> Step 3 (escalate)
  $('#psp-escalate').on('click', () => {
    transcript.push('User clicked escalate');
    $('#psp-topic').val(chosenTopic || 'other');
    $('#psp-email').val(PlatformSupport.currentUser || '');
    $('#psp-subject').val(`[${(chosenTopic || 'Support').toUpperCase()}] Help needed`);
    go(3);
  });

  // Submit ? REST
  $form.on('submit', function (e) {
    e.preventDefault();
    transcript.push('User submitted contact form');
    $('#psp-transcript').val(transcript.join('\n'));

    const formData = new FormData(this);

    $status.text('Sending…');
    fetch(PlatformSupport.restUrl, {
      method: 'POST',
      headers: { 'X-WP-Nonce': PlatformSupport.nonce },
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.ok) {
        $status.text('');
        go(4);
      } else {
        throw new Error(data && data.error ? data.error : 'Unknown error');
      }
    })
    .catch(err => {
      $status.text('Could not send. Please try again or email admin@inditech.co.in');
    });
  });

})(jQuery);
