import React from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAppShell } from '../navigation/AppShellContext';
import { colors } from '../theme/colors';

export default function Screen({ children, scroll = true, style, bottomBar }) {
  const { isInAppShell } = useAppShell();
  const content = scroll ? (
    <ScrollView contentContainerStyle={[styles.content, style]} showsVerticalScrollIndicator={false}>
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.content, styles.fill, style]}>{children}</View>
  );

  return (
    <SafeAreaView edges={isInAppShell ? ['top', 'left', 'right'] : ['top', 'bottom', 'left', 'right']} style={styles.safeArea}>
      <View style={styles.container}>
        {content}
        {!isInAppShell && bottomBar ? <View>{bottomBar}</View> : null}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.paper,
  },
  container: {
    flex: 1,
  },
  content: {
    padding: 20,
  },
  fill: {
    flex: 1,
  },
});
