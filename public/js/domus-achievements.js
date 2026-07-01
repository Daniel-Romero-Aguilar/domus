window.handleDomusBadges = function handleDomusBadges(badges) {
  if (!Array.isArray(badges) || badges.length === 0) {
    return;
  }

  badges.forEach(function (badge) {
    const text = badge && (badge.text || badge.title);
    if (!text) {
      return;
    }

    window.alert('Felicidades! Ganaste la insignia: ' + text);
  });
};

window.handleDomusAchievements = window.handleDomusBadges;
