import React, { useEffect } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors } from '../../theme/colors';

export default function SplashScreen({ navigation }) {
  useEffect(() => {
    const timer = setTimeout(() => {
      navigation.replace('Welcome');
    }, 1200);

    return () => clearTimeout(timer);
  }, [navigation]);

  return (
    <View style={styles.container}>
      <View style={styles.badge}>
        <Text style={styles.title}>TRACE</Text>
      </View>
      <Text style={styles.subtitle}>Real-time student transport management</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.sand,
    padding: 24,
  },
  badge: {
    backgroundColor: colors.ink,
    paddingHorizontal: 28,
    paddingVertical: 18,
    borderRadius: 24,
  },
  title: {
    fontSize: 40,
    fontWeight: '900',
    color: colors.white,
    letterSpacing: 2,
  },
  subtitle: {
    marginTop: 18,
    fontSize: 16,
    color: colors.deep,
    textAlign: 'center',
  },
});
