(function($){
  let currentClientId = null;

  function openModal(clientId){
    currentClientId = clientId;
    $('#smdTimerModal').addClass('is-open').attr('aria-hidden','false');
    $('#smd_desc').val('');
    $('#smd_category').val('Core update');
  }
  function closeModal(){
    currentClientId = null;
    $('#smdTimerModal').removeClass('is-open').attr('aria-hidden','true');
  }

  $(document).on('click', '.smd-start', function(e){
    e.preventDefault();
    openModal(parseInt($(this).data('client'),10));
  });

  $(document).on('click', '.smd-modal__close', function(e){
    e.preventDefault(); closeModal();
  });

  $(document).on('click', '#smdTimerStartConfirm', function(e){
    e.preventDefault();
    if(!currentClientId) return;

    const payload = {
      action: 'smd_start_timer',
      nonce: SMD_ADMIN.nonce,
      client_id: currentClientId,
      category: $('#smd_category').val(),
      description: $('#smd_desc').val()
    };

    $(this).prop('disabled', true).text('Starting...');
    $.post(SMD_ADMIN.ajax, payload)
      .done(function(res){
        if(res && res.success){
          closeModal();
          window.location.reload();
        } else {
          alert((res && res.data && res.data.message) ? res.data.message : 'Failed.');
        }
      })
      .fail(function(xhr){
        alert(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Failed.');
      })
      .always(()=> $('#smdTimerStartConfirm').prop('disabled', false).text('Start'));
  });

  $(document).on('click', '.smd-stop', function(e){
    e.preventDefault();
    const clientId = parseInt($(this).data('client'),10);
    const summary = prompt('Optional: short summary for the log entry (example: Updated 4 plugins). Leave blank to use the timer description.');
    const result = prompt('Result (success, warning, fixed):', 'success') || 'success';

    const payload = {
      action: 'smd_stop_timer',
      nonce: SMD_ADMIN.nonce,
      client_id: clientId,
      task_summary: summary || '',
      result: result
    };

    $(this).prop('disabled', true).text('Stopping...');
    $.post(SMD_ADMIN.ajax, payload)
      .done(function(res){
        if(res && res.success){
          window.location.reload();
        } else {
          alert((res && res.data && res.data.message) ? res.data.message : 'Failed.');
        }
      })
      .fail(function(xhr){
        alert(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Failed.');
      });
  });

  // close modal if clicking backdrop
  $(document).on('click', '#smdTimerModal', function(e){
    if(e.target.id === 'smdTimerModal') closeModal();
  });

})(jQuery);
