window.handleDomusAchievements = function handleDomusAchievements(achievements) {
  if (!Array.isArray(achievements) || achievements.length === 0) {
    return;
  }

  achievements.forEach(function (achievement) {
    const text = achievement && (achievement.text || achievement.title);
    if (!text) {
      return;
    }

    window.alert('Felicidades! Lograste ' + text);
  });
};
